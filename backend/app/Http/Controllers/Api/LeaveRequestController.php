<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestStatusLog;
use App\Models\User;
use App\Notifications\LeaveRequestStatusChanged;
use App\Services\LeavePolicyService;
use App\Services\WebhookNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeavePolicyService $leavePolicyService,
        private readonly WebhookNotificationService $webhookNotificationService
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = LeaveRequest::query()
            ->with([
                'user:id,name,email,role,workflow_role,available_leave_days,carry_over_leave_days,department,seniority_years',
                'processor:id,name,email',
                'statusLogs.actor:id,name,email',
            ])
            ->latest();

        if ($user->canApproveRequests()) {
            if ($request->boolean('pending_only', false)) {
                $query
                    ->where('status', 'pending')
                    ->where('user_id', '!=', $user->id)
                    ->whereRaw('(current_approval_level + 1) <= ?', [$user->approvalLevel()])
                    ->whereColumn('current_approval_level', '<', 'required_approval_level');
            }
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canCreateLeaveRequest()) {
            return response()->json([
                'message' => 'This role cannot create leave requests.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'leave_type' => ['nullable', 'in:full_day,half_day'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $leaveType = $validated['leave_type'] ?? 'full_day';

        $policy = $this->leavePolicyService->resolvePolicyFor($user);

        $requestedDays = $this->leavePolicyService->assertValidRequestWindow(
            $user,
            $policy,
            $validated['start_date'],
            $validated['end_date'],
            $leaveType
        );

        $leaveRequest = LeaveRequest::query()->create([
            'user_id' => $user->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => 'pending',
            'leave_type' => $leaveType,
            'total_days' => $requestedDays,
            'current_approval_level' => 0,
            'required_approval_level' => $policy->required_approval_level,
            'reason' => $validated['reason'] ?? null,
        ]);

        LeaveRequestStatusLog::query()->create([
            'leave_request_id' => $leaveRequest->id,
            'from_status' => null,
            'to_status' => 'pending',
            'from_level' => 0,
            'to_level' => 0,
            'acted_by' => $user->id,
            'note' => 'Request created',
            'acted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Leave request created successfully.',
            'data' => $leaveRequest->load([
                'user:id,name,email,role,workflow_role,available_leave_days,carry_over_leave_days,department,seniority_years',
                'statusLogs.actor:id,name,email',
            ]),
        ], Response::HTTP_CREATED);
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        if (! $authUser->canApproveRequests()) {
            return response()->json([
                'message' => 'Only approvers can update leave requests.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ((int) $leaveRequest->user_id === (int) $authUser->id) {
            return response()->json([
                'message' => 'You cannot approve your own leave request.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($leaveRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending requests can be processed.'],
            ]);
        }

        DB::transaction(function () use ($validated, $leaveRequest, $authUser): void {
            $leaveRequest->refresh();

            if ($leaveRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['The request has already been processed.'],
                ]);
            }

            $previousStatus = $leaveRequest->status;
            $previousLevel = $leaveRequest->current_approval_level;
            $nextLevel = $previousLevel + 1;

            if (! $authUser->canApproveAtLevel($nextLevel)) {
                throw ValidationException::withMessages([
                    'status' => ['You do not have enough approval level for this action.'],
                ]);
            }

            if ($nextLevel > $leaveRequest->required_approval_level) {
                throw ValidationException::withMessages([
                    'status' => ['The request does not require further approvals.'],
                ]);
            }

            $finalStatus = $validated['status'];

            if ($validated['status'] === 'approved' && $nextLevel < $leaveRequest->required_approval_level) {
                $finalStatus = 'pending';
            }

            if ($validated['status'] === 'approved' && $nextLevel >= $leaveRequest->required_approval_level) {
                /** @var User $employee */
                $employee = $leaveRequest->user()->lockForUpdate()->firstOrFail();

                $available = $employee->available_leave_days + $employee->carry_over_leave_days;

                if ($available < $leaveRequest->total_days) {
                    throw ValidationException::withMessages([
                        'available_leave_days' => ['The employee does not have enough leave days.'],
                    ]);
                }

                $this->leavePolicyService->consumeBalance($employee, $leaveRequest->total_days);
            }

            $leaveRequest->update([
                'status' => $finalStatus,
                'current_approval_level' => $validated['status'] === 'approved' ? $nextLevel : $previousLevel,
                'processed_by' => $authUser->id,
                'processed_at' => now(),
                'admin_note' => $validated['admin_note'] ?? null,
            ]);

            LeaveRequestStatusLog::query()->create([
                'leave_request_id' => $leaveRequest->id,
                'from_status' => $previousStatus,
                'to_status' => $finalStatus,
                'from_level' => $previousLevel,
                'to_level' => $validated['status'] === 'approved' ? $nextLevel : $previousLevel,
                'acted_by' => $authUser->id,
                'note' => $validated['admin_note'] ?? null,
                'acted_at' => now(),
            ]);
        });

        $leaveRequest->refresh();
        $leaveRequest->user->notify(new LeaveRequestStatusChanged($leaveRequest));
        $leaveRequest->load('user:id,name,email');
        $this->webhookNotificationService->broadcastLeaveStatus($leaveRequest);

        return response()->json([
            'message' => 'Leave request updated successfully.',
            'data' => $leaveRequest->load([
                'user:id,name,email,role,workflow_role,available_leave_days,carry_over_leave_days,department,seniority_years',
                'processor:id,name,email',
                'statusLogs.actor:id,name,email',
            ]),
        ]);
    }
}