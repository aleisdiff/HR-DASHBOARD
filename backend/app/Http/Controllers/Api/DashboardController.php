<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function analytics(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canViewAnalytics()) {
            return response()->json([
                'message' => 'Your role cannot access analytics.',
            ], Response::HTTP_FORBIDDEN);
        }

        $from = $request->date('from')?->startOfDay() ?? now()->startOfYear();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        $baseQuery = LeaveRequest::query()->whereBetween('created_at', [$from, $to]);

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $approved = (clone $baseQuery)->where('status', 'approved')->count();

        $approvalRate = $total > 0 ? round(($approved / $total) * 100, 2) : 0.0;

        $averageRequestedDays = round((float) ((clone $baseQuery)->avg('total_days') ?? 0), 2);

        $monthly = LeaveRequest::query()
            ->selectRaw("strftime('%Y-%m', created_at) as month")
            ->selectRaw('count(*) as total_requests')
            ->selectRaw("sum(case when status = 'approved' then 1 else 0 end) as approved_requests")
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'kpis' => [
                'total_requests' => $total,
                'pending_requests' => $pending,
                'approval_rate' => $approvalRate,
                'average_requested_days' => $averageRequestedDays,
            ],
            'monthly_trend' => $monthly,
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = now()->parse("{$month}-01")->startOfMonth();
        $end = now()->parse("{$month}-01")->endOfMonth();

        $requests = LeaveRequest::query()
            ->with('user:id,name,department')
            ->where('status', 'approved')
            ->where(function ($query) use ($start, $end): void {
                $query
                    ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($innerQuery) use ($start, $end): void {
                        $innerQuery
                            ->where('start_date', '<=', $start->toDateString())
                            ->where('end_date', '>=', $end->toDateString());
                    });
            })
            ->get();

        return response()->json([
            'data' => $requests,
            'month' => $month,
        ]);
    }
}