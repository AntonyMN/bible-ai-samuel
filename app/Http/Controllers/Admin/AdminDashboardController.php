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
                'page_views' => $m->page_views ?? 0,
                'queries' => ($m->authenticated_queries ?? 0) + ($m->unauthenticated_queries ?? 0),
                'auth_calls' => $m->authenticated_calls ?? 0,
            ];
        })->values();

        // Calculate averages
        $avgPageViews = $metrics->avg('page_views') ?? 0;
        $avgQueries = $metrics->avg(function($m) {
            return ($m->authenticated_queries ?? 0) + ($m->unauthenticated_queries ?? 0);
        }) ?? 0;
        $avgActiveUsers = $metrics->avg(function($m) {
            return count($m->active_users ?? []);
        }) ?? 0;

        // Aggregate Top Blog Posts (Last 30 days)
        $postViewsAggregated = [];
        foreach ($metrics as $m) {
            foreach ($m->post_views ?? [] as $slug => $count) {
                $postViewsAggregated[$slug] = ($postViewsAggregated[$slug] ?? 0) + $count;
            }
        }
        arsort($postViewsAggregated);
        $topPosts = array_slice($postViewsAggregated, 0, 5, true);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'today_page_views' => $todayMetrics ? ($todayMetrics->page_views ?? 0) : 0,
                'today_queries' => $todayMetrics ? (($todayMetrics->authenticated_queries ?? 0) + ($todayMetrics->unauthenticated_queries ?? 0)) : 0,
                'today_active' => $todayMetrics ? count($todayMetrics->active_users ?? []) : 0,
                'avg_page_views' => round($avgPageViews, 2),
                'avg_queries' => round($avgQueries, 2),
                'avg_active' => round($avgActiveUsers, 2),
            ],
            'graphData' => $graphData,
            'countries' => $todayMetrics ? ($todayMetrics->countries ?? []) : [],
            'topPosts' => $topPosts,
        ]);
    }
}
