<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UsageMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TrackUsage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip for static assets, internal routes, or specific paths
        if ($request->is('_debugbar*', 'sanctum/*', 'up', 'js/*', 'css/*', 'images/*')) {
            return $next($request);
        }

        return $next($request);
    }

    /**
     * Terminate the request and capture metrics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function terminate(Request $request, $response)
    {
        // Only track if it's not a debug/asset route
        if ($request->is('_debugbar*', 'sanctum/*', 'up', 'js/*', 'css/*', 'images/*', 'storage/*')) {
            return;
        }

        $today = Carbon::today()->toDateString();
        $isAuth = Auth::check();
        
        $metric = UsageMetric::where('date', $today)->first();
        if (!$metric) {
            $metric = UsageMetric::create([
                'date' => $today,
                'authenticated_calls' => 0,
                'unauthenticated_calls' => 0,
                'page_views' => 0,
                'authenticated_queries' => 0,
                'unauthenticated_queries' => 0,
                'active_users' => [],
                'countries' => [],
                'post_views' => []
            ]);
        }

        // 1. General Request Tracking (Legacy)
        if ($isAuth) {
            $metric->increment('authenticated_calls');
            $userId = (string) Auth::id();
            if (!in_array($userId, $metric->active_users ?? [])) {
                $metric->push('active_users', $userId, true);
            }
        } else {
            $metric->increment('unauthenticated_calls');
        }

        // 2. Specific Metrics
        $routeName = $request->route() ? $request->route()->getName() : null;

        // A. Chat Queries
        if ($routeName === 'chat.send') {
            if ($isAuth) {
                $metric->increment('authenticated_queries');
            } else {
                $metric->increment('unauthenticated_queries');
            }
        } 
        // B. Blog Post Views
        elseif ($routeName === 'blog.show') {
            $slug = $request->route('slug');
            if ($slug) {
                $postViews = $metric->post_views ?? [];
                $postViews[$slug] = ($postViews[$slug] ?? 0) + 1;
                $metric->post_views = $postViews;
                $metric->increment('page_views'); // Blog view is also a page view
            }
        }
        // C. General Page Views (Inertia Requests or standard GET)
        elseif ($request->isMethod('GET') && !$request->expectsJson()) {
            $metric->increment('page_views');
        }

        // 3. Country tracking (IP based lookup)
        $ip = $request->ip();
        if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
            $country = Cache::remember("ip-country-{$ip}", 86400, function () use ($ip) {
                try {
                    $res = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=countryCode");
                    if ($res->successful()) {
                        return $res->json('countryCode');
                    }
                } catch (\Exception $e) {
                    // Silently fail
                }
                return 'Unknown';
            });

            if ($country) {
                $countries = $metric->countries ?? [];
                $countries[$country] = ($countries[$country] ?? 0) + 1;
                $metric->countries = $countries;
            }
        }
        
        $metric->save();
    }
}
