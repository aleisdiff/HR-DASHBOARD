<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeavePolicy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PolicyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => LeavePolicy::query()->orderBy('department')->orderBy('seniority_min_years')->get(),
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canManagePolicies()) {
            return response()->json([
                'message' => 'Only HR and admins can manage leave policies.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'department' => ['required', 'string', 'max:100'],
            'seniority_min_years' => ['required', 'integer', 'min:0', 'max:50'],
            'max_consecutive_days' => ['required', 'integer', 'min:1', 'max:60'],
            'allow_half_day' => ['required', 'boolean'],
            'required_approval_level' => ['required', 'integer', 'min:1', 'max:5'],
            'blackout_start_date' => ['nullable', 'date'],
            'blackout_end_date' => ['nullable', 'date', 'after_or_equal:blackout_start_date'],
        ]);

        $policy = LeavePolicy::query()->updateOrCreate(
            [
                'department' => $validated['department'],
                'seniority_min_years' => $validated['seniority_min_years'],
            ],
            [
                'max_consecutive_days' => $validated['max_consecutive_days'],
                'allow_half_day' => $validated['allow_half_day'],
                'required_approval_level' => $validated['required_approval_level'],
                'blackout_start_date' => $validated['blackout_start_date'] ?? null,
                'blackout_end_date' => $validated['blackout_end_date'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Policy saved successfully.',
            'data' => $policy,
        ]);
    }
}