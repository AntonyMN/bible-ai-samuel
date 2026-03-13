<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UsageMetric;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $today = Carbon::today()->toDateString();
        
        $todayMetrics = UsageMetric::where('date', $today)->first();
        
        $metrics = UsageMetric::orderBy('date', 'desc')->limit(30)->get()->reverse();
        
        $graphData = $metrics->map(function($m) {
            return [
                'date' => Carbon::parse($m->date)->format('M d'),
                'active_users' => count($m->active_users ?? []),
                'auth_calls' => $m->authenticated_calls ?? 0,
                'unauth_calls' => $m->unauthenticated_calls ?? 0,
            ];
        })->values();

        // Calculate averages
        $avgAuth = $metrics->avg('authenticated_calls') ?? 0;
        $avgUnauth = $metrics->avg('unauthenticated_calls') ?? 0;
        $avgActiveUsers = $metrics->avg(function($m) {
            return count($m->active_users ?? []);
        }) ?? 0;

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'today_auth' => $todayMetrics->authenticated_calls ?? 0,
                'today_unauth' => $todayMetrics->unauthenticated_calls ?? 0,
                'today_active' => count($todayMetrics->active_users ?? []),
                'avg_auth' => round($avgAuth, 2),
                'avg_unauth' => round($avgUnauth, 2),
                'avg_active' => round($avgActiveUsers, 2),
            ],
            'graphData' => $graphData,
            'countries' => $todayMetrics->countries ?? [],
        ]);
    }
}
