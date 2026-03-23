<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunPodImageService
{
    protected $apiKey;
    protected $endpointId;

    public function __construct()
    {
        $this->apiKey = config('services.runpod.api_key');
        $this->endpointId = env('RUNPOD_SDXL_ENDPOINT_ID', 'djxdrz33sby1qu');
    }

    /**
     * Generate an image using RunPod SDXL
     */
    public function generateImage(string $prompt)
    {
        try {
            $response = Http::timeout(300)
                ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
                ->post("https://api.runpod.ai/v2/{$this->endpointId}/run", [
                    'input' => [
                        'prompt' => $prompt,
                        'num_inference_steps' => 25,
                        'guidance_scale' => 7.5,
                        'width' => 1024,
                        'height' => 1024,
                    ]
                ]);

            if ($response->failed()) {
                throw new \Exception("RunPod SDXL request failed: " . $response->body());
            }

            $job = $response->json();
            $jobId = $job['id'];
            
            Log::info("RunPod SDXL Job Started: {$jobId}");

            // Poll for completion
            $attempts = 0;
            while ($attempts < 60) {
                sleep(5);
                $statusResponse = Http::withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
                    ->get("https://api.runpod.ai/v2/{$this->endpointId}/status/{$jobId}");

                $statusJson = $statusResponse->json();
                $status = $statusJson['status'] ?? 'UNKNOWN';
                
                Log::info("RunPod SDXL Job ({$jobId}) Status: {$status}");

                if ($status === 'COMPLETED') {
                    $output = $statusJson['output'];
                    $imageUrl = $output['image_url'] ?? $output[0] ?? null;
                    
                    // If we have a base64 string in imageUrl/output[0], save it
                    if ($imageUrl && str_starts_with($imageUrl, 'data:image')) {
                        return $this->saveBase64Image($imageUrl);
                    }

                    // Fallback to images array if available
                    if (!$imageUrl && isset($output['images'][0])) {
                         return $this->saveBase64Image($output['images'][0]);
                    }
                    
                    if (!$imageUrl) {
                        throw new \Exception("No image URL found in completed job output: " . json_encode($output));
                    }
                    
                    return $imageUrl;
                }

                if ($status === 'FAILED') {
                    throw new \Exception("RunPod job failed: " . json_encode($statusJson));
                }

                $attempts++;
            }

            throw new \Exception("RunPod job timed out after polling for 300 seconds.");

        } catch (\Exception $e) {
            Log::error("RunPod Image Generation Error: " . $e->getMessage());
            throw $e;
        }
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
