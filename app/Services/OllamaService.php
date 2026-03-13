<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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

    public function chat(array $messages, $model = null)
    {
        if ($this->isRunPod()) {
            return $this->runPodChat($messages, $model);
        }

        $response = Http::timeout(120)->post("{$this->baseUrl}/api/chat", [
            'model' => $model ?? $this->model,
            'messages' => $messages,
            'stream' => false,
        ]);

        return $response->json();
    }

    protected function runPodChat(array $messages, $model = null)
    {
        // Combine messages into a single prompt for 'generate' method
        $prompt = "";
        foreach ($messages as $msg) {
            $role = ucfirst($msg['role']);
            $prompt .= "{$role}: {$msg['content']}\n\n";
        }
        $prompt .= "Assistant: ";

        $response = Http::timeout(120)
            ->withHeaders(['Authorization' => "Bearer {$this->runpodKey}"])
            ->post("https://api.runpod.ai/v2/{$this->runpodEndpoint}/runsync", [
                'input' => [
                    'method_name' => 'generate',
                    'input' => [
                        'model' => $model ?? $this->model,
                        'prompt' => $prompt,
                        'stream' => false,
                        'stop' => ["User:", "Assistant:", "System:", "<|end|>", "###", "Instruction:"],
                        'temperature' => 0.7,
                        'max_tokens' => 1000,
                    ]
                ]
            ]);

        $json = $response->json();
        
        // Normalize response
        if (isset($json['output']['response'])) {
            $content = $json['output']['response'];
            
            // Clean up any leaked headers that might have slipped through
            $content = preg_replace('/(Creating difficult instruction|Instruction with increased difficulty|Hard D\d+|Instruction with Added Constraints|### Instruction).*$/si', '', $content);
            $content = trim($content);

            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => $content
                ]
            ];
        }

        if (isset($json['output']['message'])) {
            return $json['output'];
        }

        return $json['output'] ?? $json;
    }

    public function embed(string $text, $model = 'nomic-embed-text')
    {
        if ($this->isRunPod()) {
            return $this->runPodEmbed($text, $model);
        }

        $response = Http::timeout(60)->post("{$this->baseUrl}/api/embeddings", [
            'model' => $model,
            'prompt' => $text,
        ]);

        return $response->json()['embedding'] ?? [];
    }

    protected function runPodEmbed(string $text, $model = 'nomic-embed-text')
    {
        $response = Http::timeout(60)
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

        $json = $response->json();
        return $json['output']['embedding'] ?? [];
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
        if ($this->isRunPod()) {
            // Return curated list of serverless models
            return [
                'models' => [
                    ['name' => 'llama3.2:3b'],
                    ['name' => 'llama3.1:8b'],
                ]
            ];
        }

        $response = Http::get("{$this->baseUrl}/api/tags");
        return $response->json();
    }
}
