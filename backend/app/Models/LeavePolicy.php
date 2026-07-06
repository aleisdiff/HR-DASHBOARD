<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department',
        'seniority_min_years',
        'max_consecutive_days',
        'allow_half_day',
        'required_approval_level',
        'blackout_start_date',
        'blackout_end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seniority_min_years' => 'integer',
            'max_consecutive_days' => 'integer',
            'allow_half_day' => 'boolean',
            'required_approval_level' => 'integer',
            'blackout_start_date' => 'date',
            'blackout_end_date' => 'date',
        ];
    }
}