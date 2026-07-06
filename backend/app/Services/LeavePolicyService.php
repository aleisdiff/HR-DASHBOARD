<?php

namespace App\Services;

use App\Models\CompanyHoliday;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

class LeavePolicyService
{
    public function resolvePolicyFor(User $user): LeavePolicy
    {
        $department = $user->department ?? 'general';
        $seniorityYears = $user->seniority_years ?? 0;

        $policy = LeavePolicy::query()
            ->where(function ($query) use ($user): void {
                $query->where('department', $user->department ?? 'general')
                    ->orWhere('department', 'general');
            })
            ->where('seniority_min_years', '<=', $seniorityYears)
            ->orderByDesc('department')
            ->orderByDesc('seniority_min_years')
            ->first();

        if ($policy) {
            return $policy;
        }

        return new LeavePolicy([
            'department' => $department,
            'seniority_min_years' => 0,
            'max_consecutive_days' => 10,
            'allow_half_day' => true,
            'required_approval_level' => 1,
        ]);
    }

    public function calculateLeaveDays(string $startDate, string $endDate, string $leaveType): float
    {
        if ($leaveType === 'half_day') {
            return 0.5;
        }

        $period = CarbonPeriod::create($startDate, $endDate);

        $holidayDates = CompanyHoliday::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $days = 0.0;

        foreach ($period as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            if (in_array($date->toDateString(), $holidayDates, true)) {
                continue;
            }

            $days += 1.0;
        }

        return $days;
    }

    public function assertValidRequestWindow(
        User $user,
        LeavePolicy $policy,
        string $startDate,
        string $endDate,
        string $leaveType
    ): float {
        if ($leaveType === 'half_day' && $startDate !== $endDate) {
            throw ValidationException::withMessages([
                'leave_type' => ['Half-day leave requests must start and end on the same date.'],
            ]);
        }

        if ($leaveType === 'half_day' && ! $policy->allow_half_day) {
            throw ValidationException::withMessages([
                'leave_type' => ['Half-day leave is not allowed by current policy.'],
            ]);
        }

        if ($policy->blackout_start_date && $policy->blackout_end_date) {
            $overlap = Carbon::parse($startDate)->lte(Carbon::parse($policy->blackout_end_date))
                && Carbon::parse($endDate)->gte(Carbon::parse($policy->blackout_start_date));

            if ($overlap) {
                throw ValidationException::withMessages([
                    'period' => ['Selected dates overlap a company blackout period.'],
                ]);
            }
        }

        $totalDays = $this->calculateLeaveDays($startDate, $endDate, $leaveType);

        if ($totalDays <= 0) {
            throw ValidationException::withMessages([
                'period' => ['Selected dates produce zero billable leave days.'],
            ]);
        }

        if ($totalDays > $policy->max_consecutive_days) {
            throw ValidationException::withMessages([
                'period' => ["Maximum allowed consecutive leave days is {$policy->max_consecutive_days}."],
            ]);
        }

        $hasOverlap = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($innerQuery) use ($startDate, $endDate): void {
                        $innerQuery->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'period' => ['The selected period overlaps an existing leave request.'],
            ]);
        }

        $pendingDays = (float) LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('total_days');

        $remainingBalance = $user->available_leave_days + $user->carry_over_leave_days - $pendingDays;

        if ($totalDays > $remainingBalance) {
            throw ValidationException::withMessages([
                'available_leave_days' => ['Insufficient remaining leave balance for this request.'],
            ]);
        }

        return $totalDays;
    }

    public function consumeBalance(User $employee, float $days): void
    {
        $remainingDays = $days;

        if ($employee->carry_over_leave_days > 0) {
            $carryToConsume = min($employee->carry_over_leave_days, (int) ceil($remainingDays));
            $employee->carry_over_leave_days -= $carryToConsume;
            $remainingDays -= $carryToConsume;
        }

        if ($remainingDays > 0) {
            $employee->available_leave_days -= (int) ceil($remainingDays);
        }

        $employee->save();
    }
}