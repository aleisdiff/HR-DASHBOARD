<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_leave_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'available_leave_days' => 20,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/leave-requests', [
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Family trip',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Leave request created successfully.')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $employee->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_approve_leave_request_and_days_are_decremented(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'available_leave_days' => 30,
            'approval_level' => 2,
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
            'available_leave_days' => 10,
        ]);

        $leaveRequest = LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
            'leave_type' => 'full_day',
            'total_days' => 3,
            'required_approval_level' => 1,
            'reason' => 'Vacation',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}/status", [
            'status' => 'approved',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Leave request updated successfully.')
            ->assertJsonPath('data.status', 'approved');

        $employee->refresh();

        $this->assertSame(7, $employee->available_leave_days);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
        ]);
    }

    public function test_employee_cannot_approve_leave_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'available_leave_days' => 20,
        ]);

        $targetEmployee = User::factory()->create([
            'role' => 'employee',
            'available_leave_days' => 20,
        ]);

        $leaveRequest = LeaveRequest::query()->create([
            'user_id' => $targetEmployee->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            'leave_type' => 'full_day',
            'total_days' => 2,
            'required_approval_level' => 1,
            'reason' => 'Appointment',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}/status", [
            'status' => 'approved',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'Only approvers can update leave requests.');
    }

    public function test_employee_can_create_half_day_leave_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'available_leave_days' => 20,
        ]);

        Sanctum::actingAs($employee);

        $date = now()->addDays(2)->toDateString();

        $response = $this->postJson('/api/leave-requests', [
            'start_date' => $date,
            'end_date' => $date,
            'leave_type' => 'half_day',
            'reason' => 'Medical appointment',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.leave_type', 'half_day')
            ->assertJsonPath('data.total_days', 0.5);
    }

    public function test_multi_level_approval_requires_second_approval(): void
    {
        $manager = User::factory()->create([
            'role' => 'admin',
            'workflow_role' => 'manager',
            'approval_level' => 1,
        ]);

        $hrAdmin = User::factory()->create([
            'role' => 'admin',
            'workflow_role' => 'hr',
            'approval_level' => 2,
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
            'available_leave_days' => 10,
        ]);

        $leaveRequest = LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            'leave_type' => 'full_day',
            'total_days' => 2,
            'required_approval_level' => 2,
            'reason' => 'Trip',
        ]);

        Sanctum::actingAs($manager);

        $firstApproval = $this->patchJson("/api/leave-requests/{$leaveRequest->id}/status", [
            'status' => 'approved',
        ]);

        $firstApproval
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.current_approval_level', 1);

        Sanctum::actingAs($hrAdmin);

        $secondApproval = $this->patchJson("/api/leave-requests/{$leaveRequest->id}/status", [
            'status' => 'approved',
        ]);

        $secondApproval
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.current_approval_level', 2);
    }

    public function test_approver_cannot_approve_own_request(): void
    {
        $manager = User::factory()->create([
            'role' => 'admin',
            'workflow_role' => 'manager',
            'approval_level' => 1,
        ]);

        $request = LeaveRequest::query()->create([
            'user_id' => $manager->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            'leave_type' => 'full_day',
            'total_days' => 2,
            'required_approval_level' => 1,
            'reason' => 'Personal leave',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->patchJson("/api/leave-requests/{$request->id}/status", [
            'status' => 'approved',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot approve your own leave request.');
    }
}