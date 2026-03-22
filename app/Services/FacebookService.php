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
    public function postToPage($message, $link)
    {
        if (!$this->pageId || !$this->accessToken) {
            Log::warning('Facebook Service: Missing Page ID or Access Token.');
            return null;
        }

        try {
            $response = Http::post("{$this->baseUrl}/{$this->pageId}/feed", [
                'message' => $message,
                'link' => $link,
                'access_token' => $this->accessToken
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Facebook Post Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Facebook Service Error: ' . $e->getMessage());
            return null;
        }
    }
}
