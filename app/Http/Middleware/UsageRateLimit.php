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
            $limit = 50;
        } else {
            $ip = $request->ip();
            $cacheKey = "rate_limit_unauth_{$ip}_{$today}";
            $limit = 20;
        }

        $currentCount = Cache::get($cacheKey, 0);

        if ($currentCount >= $limit) {
            $message = $isAuth 
                ? "You've reached the testing limit of 10 questions per day. This limit is only for the testing phase. Thank you for your patience!"
                : "You've reached the daily limit of 5 questions. Please log in for more, or come back tomorrow!";

            return response()->json([
                'error' => $message,
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
