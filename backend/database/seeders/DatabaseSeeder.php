<?php

namespace Database\Seeders;

use App\Models\CompanyHoliday;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestStatusLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@company.test'],
            [
                'name' => 'HR Admin',
                'password' => 'password123',
                'role' => 'admin',
                'workflow_role' => 'admin',
                'available_leave_days' => 30,
                'carry_over_leave_days' => 5,
                'approval_level' => 3,
                'department' => 'hr',
                'seniority_years' => 8,
            ]
        );

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@company.test'],
            [
                'name' => 'Engineering Manager',
                'password' => 'password123',
                'role' => 'admin',
                'workflow_role' => 'manager',
                'available_leave_days' => 26,
                'carry_over_leave_days' => 2,
                'approval_level' => 1,
                'department' => 'engineering',
                'seniority_years' => 6,
            ]
        );

        $hrApprover = User::query()->updateOrCreate(
            ['email' => 'hr@company.test'],
            [
                'name' => 'HR Specialist',
                'password' => 'password123',
                'role' => 'admin',
                'workflow_role' => 'hr',
                'available_leave_days' => 25,
                'carry_over_leave_days' => 1,
                'approval_level' => 2,
                'department' => 'hr',
                'seniority_years' => 5,
            ]
        );

        $employee = User::query()->updateOrCreate(
            ['email' => 'employee@company.test'],
            [
                'name' => 'Mario Rossi',
                'password' => 'password123',
                'role' => 'employee',
                'workflow_role' => 'employee',
                'available_leave_days' => 20,
                'carry_over_leave_days' => 2,
                'approval_level' => 0,
                'department' => 'engineering',
                'seniority_years' => 3,
            ]
        );

        $employeeTwo = User::query()->updateOrCreate(
            ['email' => 'employee2@company.test'],
            [
                'name' => 'Giulia Bianchi',
                'password' => 'password123',
                'role' => 'employee',
                'workflow_role' => 'employee',
                'available_leave_days' => 18,
                'carry_over_leave_days' => 1,
                'approval_level' => 0,
                'department' => 'design',
                'seniority_years' => 2,
            ]
        );

        LeavePolicy::query()->updateOrCreate(
            ['department' => 'general', 'seniority_min_years' => 0],
            [
                'max_consecutive_days' => 10,
                'allow_half_day' => true,
                'required_approval_level' => 1,
                'blackout_start_date' => null,
                'blackout_end_date' => null,
            ]
        );

        LeavePolicy::query()->updateOrCreate(
            ['department' => 'engineering', 'seniority_min_years' => 3],
            [
                'max_consecutive_days' => 12,
                'allow_half_day' => true,
                'required_approval_level' => 2,
                'blackout_start_date' => null,
                'blackout_end_date' => null,
            ]
        );

        LeavePolicy::query()->updateOrCreate(
            ['department' => 'design', 'seniority_min_years' => 0],
            [
                'max_consecutive_days' => 9,
                'allow_half_day' => true,
                'required_approval_level' => 1,
                'blackout_start_date' => null,
                'blackout_end_date' => null,
            ]
        );

        LeavePolicy::query()->updateOrCreate(
            ['department' => 'hr', 'seniority_min_years' => 0],
            [
                'max_consecutive_days' => 8,
                'allow_half_day' => true,
                'required_approval_level' => 2,
                'blackout_start_date' => null,
                'blackout_end_date' => null,
            ]
        );

        CompanyHoliday::query()->updateOrCreate(
            ['date' => now()->startOfYear()->addMonths(4)->day(1)->toDateString()],
            ['name' => 'Labour Day']
        );

        CompanyHoliday::query()->updateOrCreate(
            ['date' => now()->startOfYear()->addMonths(11)->day(25)->toDateString()],
            ['name' => 'Christmas Day']
        );

        CompanyHoliday::query()->updateOrCreate(
            ['date' => now()->startOfYear()->addMonths(7)->day(15)->toDateString()],
            ['name' => 'Ferragosto']
        );

        $pendingManagerApproval = LeaveRequest::query()->updateOrCreate(
            ['user_id' => $employee->id, 'start_date' => now()->addDays(5)->toDateString()],
            [
                'end_date' => now()->addDays(7)->toDateString(),
                'status' => 'pending',
                'leave_type' => 'full_day',
                'total_days' => 3,
                'current_approval_level' => 0,
                'required_approval_level' => 2,
                'processed_by' => null,
                'processed_at' => null,
                'admin_note' => null,
                'reason' => 'Family event in another city',
            ]
        );

        $pendingHrApproval = LeaveRequest::query()->updateOrCreate(
            ['user_id' => $employeeTwo->id, 'start_date' => now()->addDays(9)->toDateString()],
            [
                'end_date' => now()->addDays(10)->toDateString(),
                'status' => 'pending',
                'leave_type' => 'full_day',
                'total_days' => 2,
                'current_approval_level' => 1,
                'required_approval_level' => 2,
                'processed_by' => $manager->id,
                'processed_at' => now()->subHours(6),
                'admin_note' => 'Manager approved, awaiting HR final review.',
                'reason' => 'Personal travel',
            ]
        );

        $approvedRequest = LeaveRequest::query()->updateOrCreate(
            ['user_id' => $employee->id, 'start_date' => now()->subDays(14)->toDateString()],
            [
                'end_date' => now()->subDays(12)->toDateString(),
                'status' => 'approved',
                'leave_type' => 'full_day',
                'total_days' => 3,
                'current_approval_level' => 2,
                'required_approval_level' => 2,
                'processed_by' => $hrApprover->id,
                'processed_at' => now()->subDays(15),
                'admin_note' => 'Approved after manager and HR review.',
                'reason' => 'Vacation break',
            ]
        );

        $rejectedRequest = LeaveRequest::query()->updateOrCreate(
            ['user_id' => $employeeTwo->id, 'start_date' => now()->addDays(2)->toDateString()],
            [
                'end_date' => now()->addDays(4)->toDateString(),
                'status' => 'rejected',
                'leave_type' => 'full_day',
                'total_days' => 3,
                'current_approval_level' => 0,
                'required_approval_level' => 1,
                'processed_by' => $manager->id,
                'processed_at' => now()->subDay(),
                'admin_note' => 'Rejected due to sprint critical release.',
                'reason' => 'Extended weekend',
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $pendingManagerApproval->id, 'from_status' => null, 'to_status' => 'pending'],
            [
                'from_level' => 0,
                'to_level' => 0,
                'acted_by' => $employee->id,
                'note' => 'Request created by employee.',
                'acted_at' => now()->subHours(9),
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $pendingHrApproval->id, 'from_status' => null, 'to_status' => 'pending'],
            [
                'from_level' => 0,
                'to_level' => 0,
                'acted_by' => $employeeTwo->id,
                'note' => 'Request created by employee.',
                'acted_at' => now()->subDay(),
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $pendingHrApproval->id, 'from_status' => 'pending', 'to_status' => 'pending'],
            [
                'from_level' => 0,
                'to_level' => 1,
                'acted_by' => $manager->id,
                'note' => 'Manager approved level 1.',
                'acted_at' => now()->subHours(6),
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $approvedRequest->id, 'from_status' => null, 'to_status' => 'pending'],
            [
                'from_level' => 0,
                'to_level' => 0,
                'acted_by' => $employee->id,
                'note' => 'Request created by employee.',
                'acted_at' => now()->subDays(20),
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $approvedRequest->id, 'from_status' => 'pending', 'to_status' => 'pending'],
            [
                'from_level' => 0,
                'to_level' => 1,
                'acted_by' => $manager->id,
                'note' => 'Manager approved level 1.',
                'acted_at' => now()->subDays(18),
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $approvedRequest->id, 'from_status' => 'pending', 'to_status' => 'approved'],
            [
                'from_level' => 1,
                'to_level' => 2,
                'acted_by' => $hrApprover->id,
                'note' => 'HR approved final stage.',
                'acted_at' => now()->subDays(15),
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $rejectedRequest->id, 'from_status' => null, 'to_status' => 'pending'],
            [
                'from_level' => 0,
                'to_level' => 0,
                'acted_by' => $employeeTwo->id,
                'note' => 'Request created by employee.',
                'acted_at' => now()->subDays(2),
            ]
        );

        LeaveRequestStatusLog::query()->updateOrCreate(
            ['leave_request_id' => $rejectedRequest->id, 'from_status' => 'pending', 'to_status' => 'rejected'],
            [
                'from_level' => 0,
                'to_level' => 0,
                'acted_by' => $manager->id,
                'note' => 'Rejected due to business critical window.',
                'acted_at' => now()->subDay(),
            ]
        );
    }
}
