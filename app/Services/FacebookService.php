<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    protected $pageId;
    protected $accessToken;
    protected $baseUrl = 'https://graph.facebook.com/v21.0';

    public function __construct()
    {
        $this->pageId = config('services.facebook.page_id');
        $this->accessToken = config('services.facebook.access_token');
    }

    /**
     * Post a blog link to the Facebook Page
     *
     * @param string $message
     * @param string $link
     * @return array|null
     */
    public function postToPage($message, $link = null)
    {
        if (!$this->pageId || !$this->accessToken) {
            Log::warning('Facebook Service: Missing Page ID or Access Token.');
            return null;
        }

        // Using the URL query for access_token is more robust
        $url = "https://graph.facebook.com/v21.0/{$this->pageId}/feed?access_token={$this->accessToken}";
        
        $payload = [
            'message' => $message,
        ];

        if ($link) {
            $payload['link'] = $link;
        }

        try {
            Log::info("Attempting Facebook post to {$this->pageId}");
            $response = Http::asJson()->post($url, $payload);

            if ($response->failed()) {
                Log::error("Facebook Post Failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Facebook Service Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Post a photo with a caption to the Facebook Page
     *
     * @param string $caption
     * @param string $imageUrl
     * @return array|null
     */
    public function postPhoto($caption, $imageUrl)
    {
        if (!$this->pageId || !$this->accessToken) {
            Log::warning('Facebook Service: Missing Page ID or Access Token.');
            return null;
        }

        $url = "https://graph.facebook.com/v21.0/{$this->pageId}/photos?access_token={$this->accessToken}";
        
        $payload = [
            'url' => $imageUrl,
            'caption' => $caption,
        ];

        try {
            Log::info("Attempting Facebook photo post to {$this->pageId}");
            $response = Http::asJson()->post($url, $payload);

            if ($response->failed()) {
                Log::error("Facebook Photo Post Failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Facebook Photo Service Exception: " . $e->getMessage());
            return null;
        }
    }
}
