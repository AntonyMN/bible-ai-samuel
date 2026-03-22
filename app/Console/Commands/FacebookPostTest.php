<?php

namespace App\Console\Commands;

use App\Services\FacebookService;
use Illuminate\Console\Command;

class FacebookPostTest extends Command
{
    protected $signature = 'samuel:facebook-test';
    protected $description = 'Test Facebook Page posting';

    public function handle(FacebookService $facebook)
    {
        $this->info("Attempting to post a test message to Facebook Page...");

        $message = "Hello from Samuel AI! 🌟 I'm now connected to Facebook to share scriptural reflections and daily wisdom. God bless you all! #SamuelAI #Faith #Technology";
        $link = "https://blog.chatwithsamuel.org";

        $response = $facebook->postToPage($message, $link);

        if ($response && isset($response['id'])) {
            $this->info("Test post successful! Post ID: " . $response['id']);
            return 0;
        }

        $this->error("Failed to post to Facebook. Check logs for details.");
        return 1;
    }
}
