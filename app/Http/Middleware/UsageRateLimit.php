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
            $userId = Auth::id();
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

        $response = $next($request);

        // Only increment if it's a successful processing of the request
        // (e.g. not a validation error, though we could count those too)
        if ($response->getStatusCode() < 400) {
            Cache::put($cacheKey, $currentCount + 1, now()->endOfDay());
        }

        return $response;
    }
}
