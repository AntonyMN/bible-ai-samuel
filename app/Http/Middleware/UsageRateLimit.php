<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        $response = $next($request);

        // Only apply soft-prompt for unauthenticated users
        if (!Auth::check() && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $ip = $request->ip();
            $today = Carbon::today()->toDateString();
            $cacheKey = "soft_prompt_unauth_{$ip}_{$today}";
            
            $count = Cache::get($cacheKey, 0) + 1;
            Cache::put($cacheKey, $count, now()->endOfDay());

            if ($count % 5 == 0) {
                // If the response is JSON, we can append the prompt
                $content = $response->getContent();
                $data = json_decode($content, true);
                
                if (is_array($data)) {
                    $data['prompt_registration'] = true;
                    $data['registration_message'] = "You've asked {$count} questions today! Register for a free account to save your history and access all Bible versions.";
                    $response->setContent(json_encode($data));
                }
            }
        }

        return $response;
    }
}
