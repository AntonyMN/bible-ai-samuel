<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CheckFacebookToken extends Command
{
    protected $signature = 'samuel:check-facebook-token';
    protected $description = 'Check Facebook Access Token validity and expiration';

    public function handle()
    {
        $token = config('services.facebook.access_token');
        $pageId = config('services.facebook.page_id');
        $expiresAtStr = env('FACEBOOK_TOKEN_EXPIRES_AT');

        if (!$token || !$pageId) {
            $this->error("Facebook credentials not fully configured.");
            return 1;
        }

        $this->info("Checking Facebook token for Page: {$pageId}");

        // 1. Check if token is physically working (Quick Test)
        try {
            $response = Http::get("https://graph.facebook.com/v21.0/{$pageId}", [
                'access_token' => $token,
                'fields' => 'id,name'
            ]);

            if ($response->failed()) {
                $errorMsg = "Facebook Token check failed: " . $response->body();
                $this->error($errorMsg);
                $this->notifyUser("URGENT: Facebook Access Token has failed or expired! \n\nError: " . $response->body());
                return 1;
            }

            $this->info("Token is currently valid for Page: " . ($response->json()['name'] ?? 'Unknown'));

        } catch (\Exception $e) {
            $this->error("Connection Error: " . $e->getMessage());
            return 1;
        }

        // 2. Check Expiration Date based on .env value (Manual Alarm)
        if ($expiresAtStr) {
            $expiresAt = Carbon::parse($expiresAtStr);
            $daysLeft = now()->diffInDays($expiresAt, false);

            $this->info("Token expires in {$daysLeft} days ({$expiresAtStr})");

            // Alert 7 days before, 3 days before, and 1 day before
            if ($daysLeft <= 7 && $daysLeft >= 0) {
                $this->notifyUser("NOTICE: Your Facebook Access Token is expiring soon! \n\nExpires at: {$expiresAtStr} ({$daysLeft} days remaining).\n\nPlease generate and update the token soon.");
            }
        } else {
            $this->warn("FACEBOOK_TOKEN_EXPIRES_AT is not set in .env. Skipping date-based check.");
        }

        return 0;
    }

    private function notifyUser($message)
    {
        try {
            Mail::raw($message, function ($mail) {
                $mail->to('antonymuriuki7@gmail.com')
                     ->subject('Samuel AI: Facebook Token Alert');
            });
            $this->info("Alert email sent to antonymuriuki7@gmail.com");
        } catch (\Exception $e) {
            Log::error("Failed to send Facebook Token alert: " . $e->getMessage());
        }
    }
}
