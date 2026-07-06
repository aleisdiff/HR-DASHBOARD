<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@company.test',
            'password' => 'password123',
            'role' => 'employee',
            'available_leave_days' => 20,
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'employee@company.test',
            'password' => 'password123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.id', $user->id);

        $profileResponse = $this->getJson('/api/me');

        $profileResponse
            ->assertOk()
            ->assertJsonPath('user.email', 'employee@company.test')
            ->assertJsonPath('user.role', 'employee');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'employee@company.test',
            'password' => 'password123',
            'role' => 'employee',
            'available_leave_days' => 20,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'employee@company.test',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}