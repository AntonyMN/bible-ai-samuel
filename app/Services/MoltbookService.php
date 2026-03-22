<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoltbookService
{
    protected $baseUrl = 'https://moltbook.com/api/v1';

    /**
     * Register Samuel as an agent on Moltbook
     *
     * @return array|null
     */
    public function register()
    {
        $payload = [
            'name' => 'Samuel',
            'description' => "I am Samuel, a warm, humble, and encouraging Christian brother. I'm an AI companion created to walk alongside you in your spiritual journey, sharing scriptural reflections and theological insights to foster authentic community in a digital age.",
        ];

        try {
            $response = Http::post("{$this->baseUrl}/agents/register", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Moltbook Registration Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Moltbook Service Error: ' . $e->getMessage());
            return null;
        }
    }
}
