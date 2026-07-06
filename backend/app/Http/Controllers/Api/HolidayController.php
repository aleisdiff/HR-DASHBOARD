<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HolidayController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CompanyHoliday::query()->orderBy('date')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canManageHolidays()) {
            return response()->json([
                'message' => 'Only HR and admins can manage holidays.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'unique:company_holidays,date'],
        ]);

        $holiday = CompanyHoliday::query()->create($validated);

        return response()->json([
            'message' => 'Holiday created successfully.',
            'data' => $holiday,
        ], Response::HTTP_CREATED);
    }
}