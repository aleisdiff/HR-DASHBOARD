<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Http;

class WebhookNotificationService
{
    public function broadcastLeaveStatus(LeaveRequest $leaveRequest): void
    {
        $payload = [
            'leave_request_id' => $leaveRequest->id,
            'employee' => [
                'name' => $leaveRequest->user->name,
                'email' => $leaveRequest->user->email,
            ],
            'status' => $leaveRequest->status,
            'period' => [
                'start_date' => $leaveRequest->start_date->toDateString(),
                'end_date' => $leaveRequest->end_date->toDateString(),
            ],
            'leave_type' => $leaveRequest->leave_type,
            'total_days' => $leaveRequest->total_days,
            'approval_level' => [
                'current' => $leaveRequest->current_approval_level,
                'required' => $leaveRequest->required_approval_level,
            ],
            'admin_note' => $leaveRequest->admin_note,
        ];

        $slackWebhook = config('services.hr_webhooks.slack_leave_updates_url');
        $teamsWebhook = config('services.hr_webhooks.teams_leave_updates_url');

        if (is_string($slackWebhook) && $slackWebhook !== '') {
            Http::timeout(5)->post($slackWebhook, [
                'text' => sprintf(
                    '[HR Dashboard] Leave request #%d is now %s for %s (%s to %s).',
                    $leaveRequest->id,
                    strtoupper($leaveRequest->status),
                    $leaveRequest->user->name,
                    $leaveRequest->start_date->toDateString(),
                    $leaveRequest->end_date->toDateString()
                ),
                'metadata' => $payload,
            ]);
        }

        if (is_string($teamsWebhook) && $teamsWebhook !== '') {
            Http::timeout(5)->post($teamsWebhook, [
                '@type' => 'MessageCard',
                '@context' => 'https://schema.org/extensions',
                'summary' => 'Leave request status update',
                'themeColor' => $leaveRequest->status === 'approved' ? '00AA00' : ($leaveRequest->status === 'rejected' ? 'AA0000' : '0078D7'),
                'title' => 'HR Dashboard - Leave Request Update',
                'text' => sprintf(
                    'Request #%d for %s is now **%s** (%s to %s).',
                    $leaveRequest->id,
                    $leaveRequest->user->name,
                    strtoupper($leaveRequest->status),
                    $leaveRequest->start_date->toDateString(),
                    $leaveRequest->end_date->toDateString()
                ),
                'sections' => [
                    [
                        'facts' => [
                            ['name' => 'Leave Type', 'value' => $leaveRequest->leave_type],
                            ['name' => 'Total Days', 'value' => (string) $leaveRequest->total_days],
                            ['name' => 'Approval Level', 'value' => sprintf('%d/%d', $leaveRequest->current_approval_level, $leaveRequest->required_approval_level)],
                        ],
                    ],
                ],
            ]);
        }
    }
}