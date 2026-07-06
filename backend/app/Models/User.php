<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'workflow_role',
        'available_leave_days',
        'carry_over_leave_days',
        'approval_level',
        'department',
        'seniority_years',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'available_leave_days' => 'integer',
            'carry_over_leave_days' => 'integer',
            'approval_level' => 'integer',
            'seniority_years' => 'integer',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function workflowRole(): string
    {
        if ($this->workflow_role === null) {
            return $this->isAdmin() ? 'admin' : 'employee';
        }

        if ($this->workflow_role === 'employee' && $this->isAdmin()) {
            return 'admin';
        }

        return $this->workflow_role;
    }

    public function isEmployee(): bool
    {
        return $this->workflowRole() === 'employee';
    }

    public function isManager(): bool
    {
        return $this->workflowRole() === 'manager';
    }

    public function isHr(): bool
    {
        return $this->workflowRole() === 'hr';
    }

    public function canApproveRequests(): bool
    {
        return in_array($this->workflowRole(), ['manager', 'hr', 'admin'], true);
    }

    public function canManagePolicies(): bool
    {
        return in_array($this->workflowRole(), ['hr', 'admin'], true);
    }

    public function canManageHolidays(): bool
    {
        return in_array($this->workflowRole(), ['hr', 'admin'], true);
    }

    public function canViewAnalytics(): bool
    {
        return $this->canApproveRequests();
    }

    public function canCreateLeaveRequest(): bool
    {
        return ! $this->isAdmin();
    }

    public function handledLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'processed_by');
    }

    public function approvalLevel(): int
    {
        if ($this->approval_level !== null) {
            return $this->approval_level;
        }

        return match ($this->workflowRole()) {
            'manager' => 1,
            'hr' => 2,
            'admin' => 3,
            default => 0,
        };
    }

    public function canApproveAtLevel(int $level): bool
    {
        return $this->approvalLevel() >= $level;
    }

    public function unreadNotificationsList(): HasMany
    {
        return $this->hasMany(DatabaseNotification::class, 'notifiable_id')
            ->whereNull('read_at')
            ->where('notifiable_type', self::class);
    }
}
