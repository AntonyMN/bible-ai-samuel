<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\UsageMetric;
use Carbon\Carbon;

class UsageRateLimit
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
        // Don't rate limit admins
        if (Auth::check() && Auth::user()->is_admin) {
            return $next($request);
        }

        $today = Carbon::today()->toDateString();
        $isAuth = Auth::check();
        
        if ($isAuth) {
            $userId = (string) Auth::id();
            $cacheKey = "rate_limit_auth_{$userId}_{$today}";
            $limit = 10;
        } else {
            $ip = $request->ip();
            $cacheKey = "rate_limit_unauth_{$ip}_{$today}";
            $limit = 5;
        }

        $currentCount = Cache::get($cacheKey, 0);

        if ($currentCount >= $limit) {
            return response()->json([
                'error' => "Daily limit reached ({$limit} requests). This is currently limited for testing purposes. Please try again tomorrow.",
            ], 429);
        }

        return $next($request);
    }

    /**
     * Terminate the request and increment rate limit if successful.
     */
    public function terminate(Request $request, $response)
    {
        // Don't limit admins
        if (Auth::check() && Auth::user()->is_admin) {
            return;
        }

        // Only increment if successful (status 200-299)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $today = Carbon::today()->toDateString();
            $isAuth = Auth::check();
            
            if ($isAuth) {
                $userId = (string) Auth::id();
                $cacheKey = "rate_limit_auth_{$userId}_{$today}";
            } else {
                $ip = $request->ip();
                $cacheKey = "rate_limit_unauth_{$ip}_{$today}";
            }

            $currentCount = Cache::get($cacheKey, 0);
            Cache::put($cacheKey, $currentCount + 1, now()->endOfDay());
        }
    }
}
