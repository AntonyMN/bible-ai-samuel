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

        $response = $next($request);

        // Only track successful or redirect responses to avoid pollution from 404s/etc if desired
        // For now, let's track everything that gets past middleware
        
        $today = Carbon::today()->toDateString();
        $isAuth = Auth::check();
        
        $metric = UsageMetric::firstOrCreate(
            ['date' => $today],
            [
                'authenticated_calls' => 0,
                'unauthenticated_calls' => 0,
                'active_users' => [], // We'll store user IDs to count unique active users
                'countries' => []
            ]
        );

        if ($isAuth) {
            $metric->increment('authenticated_calls');
            $userId = Auth::id();
            if (!in_array($userId, $metric->active_users ?? [])) {
                $metric->push('active_users', $userId, true);
            }
        } else {
            $metric->increment('unauthenticated_calls');
        }

        // Country tracking (IP based lookup)
        $ip = $request->ip();
        if ($ip && $ip !== '127.0.0.1') {
            $country = Cache::remember("ip-country-{$ip}", 86400, function () use ($ip) {
                try {
                    $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=countryCode");
                    if ($response->successful()) {
                        return $response->json('countryCode');
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
                $metric->save();
            }
        }

        return $response;
    }
}
