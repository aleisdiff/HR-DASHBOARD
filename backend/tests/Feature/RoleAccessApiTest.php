<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_analytics(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'workflow_role' => 'employee',
            'approval_level' => 0,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/dashboard/analytics');

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'Your role cannot access analytics.');
    }

    public function test_manager_can_access_analytics(): void
    {
        $manager = User::factory()->create([
            'role' => 'admin',
            'workflow_role' => 'manager',
            'approval_level' => 1,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/dashboard/analytics');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'kpis' => ['total_requests', 'pending_requests', 'approval_rate', 'average_requested_days'],
                'monthly_trend',
            ]);
    }

    public function test_hr_can_manage_policy_and_holiday(): void
    {
        $hr = User::factory()->create([
            'role' => 'admin',
            'workflow_role' => 'hr',
            'approval_level' => 2,
        ]);

        Sanctum::actingAs($hr);

        $policyResponse = $this->postJson('/api/policies', [
            'department' => 'operations',
            'seniority_min_years' => 2,
            'max_consecutive_days' => 8,
            'allow_half_day' => true,
            'required_approval_level' => 2,
            'blackout_start_date' => null,
            'blackout_end_date' => null,
        ]);

        $policyResponse
            ->assertOk()
            ->assertJsonPath('message', 'Policy saved successfully.');

        $holidayResponse = $this->postJson('/api/holidays', [
            'name' => 'Founders Day',
            'date' => now()->addMonth()->startOfMonth()->toDateString(),
        ]);

        $holidayResponse
            ->assertCreated()
            ->assertJsonPath('message', 'Holiday created successfully.');
    }

    public function test_manager_cannot_manage_policy_or_holiday(): void
    {
        $manager = User::factory()->create([
            'role' => 'admin',
            'workflow_role' => 'manager',
            'approval_level' => 1,
        ]);

        Sanctum::actingAs($manager);

        $policyResponse = $this->postJson('/api/policies', [
            'department' => 'operations',
            'seniority_min_years' => 2,
            'max_consecutive_days' => 8,
            'allow_half_day' => true,
            'required_approval_level' => 2,
            'blackout_start_date' => null,
            'blackout_end_date' => null,
        ]);

        $policyResponse
            ->assertForbidden()
            ->assertJsonPath('message', 'Only HR and admins can manage leave policies.');

        $holidayResponse = $this->postJson('/api/holidays', [
            'name' => 'Founders Day',
            'date' => now()->addMonth()->startOfMonth()->toDateString(),
        ]);

        $holidayResponse
            ->assertForbidden()
            ->assertJsonPath('message', 'Only HR and admins can manage holidays.');
    }
}
