<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected $baseUrl;
    protected $model;
    protected $runpodKey;
    protected $runpodEndpoint;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url', env('OLLAMA_URL', 'http://localhost:11434'));
        $this->model = config('services.ollama.model', env('OLLAMA_DEFAULT_MODEL', 'llama3.2:3b'));
        $this->runpodKey = config('services.runpod.api_key');
        $this->runpodEndpoint = config('services.runpod.endpoint_id');
    }

    protected function isRunPod()
    {
        return !empty($this->runpodKey) && !empty($this->runpodEndpoint);
    }

    public function chat(array $messages, $model = null, $stop = null)
    {
        if ($this->isCircuitBroken()) {
            throw new \Exception("GPU connectivity is temporarily suspended due to repeated failures (Circuit Breaker).");
        }

        if ($this->isRunPod()) {
            $response = $this->runPodChat($messages, $model, $stop);
        } else {
            try {
                $rawResponse = Http::timeout(180)->post("{$this->baseUrl}/api/chat", [
                    'name' => $model ?? $this->model,
                    'messages' => $messages,
                    'stream' => false,
                ]);
                $this->recordSuccess();
                $response = $rawResponse->json();
            } catch (\Exception $e) {
                $this->recordFailure();
                throw $e;
            }
        }

        // UNIVERSAL NORMALIZATION: Apply cleaning to EVERY response
        return $this->normalizeResponse($response);
    }

    protected function runPodChat(array $messages, $model = null, $stop = null)
    {
        // Construct a standard Llama-style Chat prompt for RunPod
        $prompt = "";
        foreach ($messages as $msg) {
            $role = ($msg['role'] === 'system') ? 'system' : $msg['role'];
            $prompt .= "<|start_header_id|>{$role}<|end_header_id|>\n\n{$msg['content']}<|eot_id|>";
        }
        $prompt .= "<|start_header_id|>assistant<|end_header_id|>\n\n";

        try {
            $response = Http::timeout(120)
                ->withHeaders(['Authorization' => "Bearer {$this->runpodKey}"])
                ->post("https://api.runpod.ai/v2/{$this->runpodEndpoint}/runsync", [
                    'input' => [
                        'method_name' => 'generate',
                        'input' => [
                            'model' => $model ?? $this->model,
                            'prompt' => $prompt,
                            'stream' => false,
                            'stop' => $stop ?? ["### User:", "### Assistant:", "### System:", "### Instruction:", "<|end|>", "Your task:", "Task:", "Pastor"],
                            'temperature' => 0.6,
                            'max_tokens' => 1000,
                        ]
                    ]
                ]);
            $this->recordSuccess();
        } catch (\Exception $e) {
            $this->recordFailure();
            Log::error("RunPod Chat HTTP Error: " . $e->getMessage());
            throw $e;
        }

        $json = $response->json();
        Log::info("RunPod Chat RAW Response: " . json_encode($json));
        
        return $json['output'] ?? $json;
    }

    /**
     * Universal response cleaner to prevent hallucinations like "### User:", "Nowadays...", etc.
     */
    protected function normalizeResponse($json)
    {
        $content = '';
        
        if (isset($json['message']['content'])) {
            $content = $json['message']['content'];
        } elseif (isset($json['response'])) {
            $content = $json['response'];
        } elseif (is_string($json)) {
            $content = $json;
        }

        if (!empty($content)) {
            // 1. Kill prompt injection/drift hallucinations (The "Augustus/Nowadays" issue)
            $content = preg_replace('/(Creating difficult instruction|Instruction with increased difficulty|Hard D\d+|Instruction with Added Constraints|### Instruction|Solution to Instruction|Difficulty Level|Much More Diff|Your task is to act as|Pastor Johnathan|Light of Eden|Sunday service time|The system is to engage|as if you are Samuel Blythe|System Documentation|Rolex system|JSONPlaceholder|Augustus|Solaris Group|Tableau Review|Instruction Finder|Nowadays\. Please constructing).*$/si', '', $content);
            
            // 2. Kill leaked Bible version headers
            $content = preg_replace('/(\()? (NLT|NASB|NIV|KJV|NKJV|ESV|RSV) (\))?.*$/mi', '', $content);
            
            // 3. Kill hallucinated conversation prompts (The "### User:" loop)
            $content = preg_replace('/(\n(User|Assistant|System|###|Task|Ask|Instruction):.*$)/si', '', $content);
            
            // 4. Kill standard chat markers and cleanup whitespace
            $content = preg_replace('/^\[Response\]:?\s*/i', '', $content);
            $content = trim($content);
            
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => $content
                ]
            ];
        }

        return $json;
    }

    public function embed(string $text, $model = 'nomic-embed-text')
    {
        if ($this->isCircuitBroken()) {
            return [];
        }

        if ($this->isRunPod()) {
            return $this->runPodEmbed($text, $model);
        }

        try {
            $response = Http::timeout(300)->post("{$this->baseUrl}/api/embeddings", [
                'model' => $model,
                'prompt' => $text,
            ]);
            $this->recordSuccess();
            return $response->json()['embedding'] ?? [];
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    protected function runPodEmbed(string $text, $model = 'nomic-embed-text')
    {
        try {
            $response = Http::timeout(300)
                ->withHeaders(['Authorization' => "Bearer {$this->runpodKey}"])
                ->post("https://api.runpod.ai/v2/{$this->runpodEndpoint}/runsync", [
                    'input' => [
                        'method_name' => 'embeddings',
                        'input' => [
                            'model' => $model,
                            'prompt' => $text,
                        ]
                    ]
                ]);
            $this->recordSuccess();
            $json = $response->json();
            return $json['output']['embedding'] ?? [];
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function getEmbeddings(array $texts, $model = 'nomic-embed-text')
    {
        if ($this->isCircuitBroken()) {
            return [];
        }

        if ($this->isRunPod()) {
            return $this->runPodEmbeddings($texts, $model);
        }

        try {
            $response = Http::timeout(300)->post("{$this->baseUrl}/api/embed", [
                'model' => $model,
                'input' => $texts,
            ]);
            $this->recordSuccess();
            return $response->json()['embeddings'] ?? [];
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    protected function runPodEmbeddings(array $texts, $model = 'nomic-embed-text')
    {
        try {
            $allEmbeddings = [];
            foreach ($texts as $text) {
                $allEmbeddings[] = $this->runPodEmbed($text, $model);
            }
            return $allEmbeddings;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function generateStream(array $messages, $model = null)
    {
        if ($this->isRunPod()) {
            // RunPod sync endpoints don't typically support native HTTP streaming in this way
            // We'll just call the sync version for now or log a warning
            return $this->chat($messages, $model);
        }

        return Http::withOptions(['stream' => true])
            ->post("{$this->baseUrl}/api/chat", [
                'model' => $model ?? $this->model,
                'messages' => $messages,
                'stream' => true,
            ]);
    }

    public function listModels()
    {
        return Cache::remember('ollama_models_list', 3600, function () {
            if ($this->isRunPod()) {
                // Return curated list of serverless models
                return [
                    'models' => [
                        ['name' => 'llama3.2:3b'],
                        ['name' => 'llama3.1:8b'],
                    ]
                ];
            }

            try {
                $response = Http::timeout(2)->get("{$this->baseUrl}/api/tags");
                return $response->json();
            } catch (\Exception $e) {
                return ['models' => []];
            }
        });
    }

    protected function isCircuitBroken()
    {
        return Cache::has('ollama_circuit_broken');
    }

    protected function recordFailure()
    {
        $count = Cache::increment('ollama_failure_count');
        if ($count >= 3) {
            Log::warning("Circuit breaker triggered for Ollama service. Suspending requests for 15 minutes.");
            Cache::put('ollama_circuit_broken', true, now()->addMinutes(15));
            Cache::forget('ollama_failure_count');
        }
    }

    protected function recordSuccess()
    {
        Cache::forget('ollama_failure_count');
    }
}
