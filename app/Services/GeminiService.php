<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements AiServiceInterface
{
    protected $apiKey;
    protected $baseUrl;
    protected $model;
    protected $embeddingModel;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        $this->embeddingModel = config('services.gemini.embedding_model', 'text-embedding-004');
        $this->baseUrl = "https://generativelanguage.googleapis.com/v1beta/models";
    }

    /**
     * Chat with Gemini
     * 
     * @param array $messages Standard Samule messages array [['role' => 'system|user|assistant', 'content' => '...']]
     * @param string|null $model Optional model override
     * @param array|null $stop Optional stop sequences
     * @return array Consistent response format for ProcessSamuelResponse
     */
    public function chat(array $messages, $model = null, $stop = null)
    {
        $systemInstruction = "";
        $contents = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemInstruction = $message['content'];
                continue;
            }

            $role = ($message['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $message['content']]
                ]
            ];
        }

        $payload = [
            'contents' => $contents,
        ];

        if (!empty($systemInstruction)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        $model = $model ?? $this->model;
        
        try {
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/{$model}:generateContent?key={$this->apiKey}", $payload);

            if (!$response->successful()) {
                Log::error("Gemini API Error: " . $response->body());
                throw new \Exception("Gemini API Error: " . $response->status());
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Return in the same format OllamaService was providing for backward compatibility
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => trim($text)
                ]
            ];
        } catch (\Exception $e) {
            Log::error("Gemini Service Exception: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get embedding for a single text
     */
    public function embed(string $text, $model = null)
    {
        $model = $model ?? $this->embeddingModel;
        
        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/{$model}:embedContent?key={$this->apiKey}", [
                    'model' => "models/{$model}",
                    'content' => [
                        'parts' => [
                            ['text' => $text]
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                throw new \Exception("Gemini Embedding Error: " . $response->body());
            }

            return $response->json()['embedding']['values'] ?? [];
        } catch (\Exception $e) {
            Log::error("Gemini Embed Exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get embeddings for multiple texts
     */
    public function getEmbeddings(array $texts, $model = null)
    {
        $model = $model ?? $this->embeddingModel;
        $requests = [];

        // Ensure model name has 'models/' prefix if not present for the payload
        $payloadModelName = str_starts_with($model, 'models/') ? $model : "models/{$model}";

        foreach ($texts as $text) {
            $requests[] = [
                'model' => $payloadModelName,
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];
        }

        try {
            // The URL should use the model name directly, not prefixed with 'models/'
            $urlModelName = str_replace('models/', '', $model);
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/{$urlModelName}:batchEmbedContents?key={$this->apiKey}", [
                    'requests' => $requests
                ]);

            if (!$response->successful()) {
                throw new \Exception("Gemini Batch Embedding Error: " . $response->body());
            }

            $embeddings = [];
            foreach ($response->json()['embeddings'] ?? [] as $item) {
                $embeddings[] = $item['values'] ?? [];
            }

            return $embeddings;
        } catch (\Exception $e) {
            Log::error("Gemini Batch Embed Exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * List available models
     */
    public function listModels()
    {
        return [
            'models' => [
                ['name' => 'gemini-1.5-flash'],
                ['name' => 'gemini-1.5-pro'],
            ]
        ];
    }
}
