<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'status',
        'leave_type',
        'total_days',
        'current_approval_level',
        'required_approval_level',
        'processed_by',
        'processed_at',
        'admin_note',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'processed_at' => 'datetime',
            'total_days' => 'float',
            'current_approval_level' => 'integer',
            'required_approval_level' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(LeaveRequestStatusLog::class)->latest('acted_at');
    }
}