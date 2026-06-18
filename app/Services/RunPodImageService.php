<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunPodImageService
{
    protected $apiKey;
    protected $model;
    protected $overlayService;

    public function __construct(ScriptureOverlayService $overlayService)
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = 'imagen-4.0-fast-generate-001';
        $this->overlayService = $overlayService;
    }

    /**
     * Generate an image with a scripture overlay
     */
    public function generateWithOverlay(string $prompt, string $verse, string $reference)
    {
        $imageUrl = $this->generateImage($prompt);
        if ($imageUrl) {
            return $this->overlayService->overlay($imageUrl, $verse, $reference);
        }
        return null;
    }

    /**
     * Generate an image using Google Gemini API Imagen
     */
    public function generateImage(string $prompt)
    {
        $enhancedPrompt = $this->enhancePrompt($prompt);
        Log::info("Generating image with Gemini Imagen 4 (Fast). Enhanced Prompt: " . $enhancedPrompt);

        try {
            $response = Http::timeout(60)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:predict?key={$this->apiKey}", [
                    'instances' => [
                        [
                            'prompt' => $enhancedPrompt
                        ]
                    ],
                    'parameters' => [
                        'sampleCount' => 1,
                        'aspectRatio' => '1:1',
                        'outputMimeType' => 'image/png'
                    ]
                ]);

            if ($response->failed()) {
                throw new \Exception("Gemini Imagen request failed: " . $response->body());
            }

            $data = $response->json();
            $base64Image = $data['predictions'][0]['bytesBase64Encoded'] ?? null;

            if (!$base64Image) {
                throw new \Exception("No image data found in prediction response: " . json_encode($data));
            }

            Log::info("Gemini Imagen image generation successful.");
            return $this->saveBase64Image($base64Image);

        } catch (\Exception $e) {
            Log::error("Gemini Imagen Generation Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enhance prompt with Samuel's artistic taste/style
     */
    protected function enhancePrompt(string $prompt): string
    {
        // Strip out "reverent Christian art:" prefix if already present to prevent duplication
        $cleanPrompt = preg_replace('/^reverent Christian art:\s*/i', '', $prompt);
        
        return "reverent Christian art, classical biblical oil painting style, warm soft natural lighting, peaceful, sacred atmosphere, detailed realism, spiritual, holy, high-quality masterpiece: " . $cleanPrompt;
    }

    protected function saveBase64Image($base64String)
    {
        // Strip data URI prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String)) {
            $base64String = substr($base64String, strpos($base64String, ',') + 1);
        }

        $image = base64_decode($base64String);
        $filename = 'blog_images/' . Str::random(40) . '.png';
        
        // Ensure directory exists
        if (!Storage::disk('public')->exists('blog_images')) {
            Storage::disk('public')->makeDirectory('blog_images');
        }

        Storage::disk('public')->put($filename, $image);
        return Storage::url($filename);
    }
}
