<?php

namespace App\Services;

use App\Models\Memory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    protected $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    /**
     * Inject active memories into the system prompt.
     */
    public function getInjectedContext($userId)
    {
        $memories = Memory::where('user_id', $userId)
            ->where('is_completed', '!=', true)
            ->orderBy('importance', 'desc')
            ->limit(10)
            ->get();

        if ($memories->isEmpty()) {
            return "";
        }

        $context = "\n### VITAL USER INFORMATION (Long-Term Memory)\n";
        $context .= "Samuel, you remember these important things about the user. Reference them naturally if relevant:\n";
        
        foreach ($memories as $memory) {
            $context .= "- " . $memory->content . "\n";
        }
        
        return $context . "\n";
    }

    /**
     * Extract new memories from a user message.
     */
    public function extractMemories($userId, $userMessage, $conversationId = null)
    {
        $prompt = "You are a memory extractor for Samuel, a Christian AI companion. " .
            "Extract specific, vital facts about the user's life (plans, events, struggles, happy occurrences, preferences) from the message below. " .
            "Return a JSON array of objects with 'content', 'category', and 'importance' (1-5). " .
            "Categories: plan, struggle, event, preference, other. " .
            "Rules:\n" .
            "- Content must be concise (e.g., 'Has a wedding this Saturday', 'Struggling with anxiety at work').\n" .
            "- Only extract personal life details. Ignore general questions or bible talk.\n" .
            "- Importance: 5 for critical life events/struggles, 1 for minor preferences.\n" .
            "User Message: \"$userMessage\"\n" .
            "JSON Output:";

        try {
            $messages = [['role' => 'user', 'content' => $prompt]];
            $response = $this->ollama->chat($messages, config('services.ollama.model'));
            
            $aiContent = '';
            if (isset($response['message']['content'])) {
                $aiContent = $response['message']['content'];
            }
            
            $jsonStr = $this->cleanJson($aiContent);
            $data = json_decode($jsonStr, true);

            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['content']) && !empty($item['content'])) {
                        Memory::create([
                            'user_id' => $userId,
                            'content' => $item['content'],
                            'category' => $item['category'] ?? 'other',
                            'importance' => $item['importance'] ?? 3,
                            'is_completed' => false,
                            'metadata' => [
                                'conversation_id' => $conversationId,
                                'source_message' => $userMessage,
                            ]
                        ]);
                        Log::info("Samuel remembered: " . $item['content']);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Memory extraction failed: " . $e->getMessage());
        }
    }

    protected function cleanJson($text)
    {
        // Simple cleanup to handle Markdown code blocks or leading/trailing text
        $text = preg_replace('/^.*?\[/s', '[', $text);
        $text = preg_replace('/\][^\]]*?$/s', ']', $text);
        return $text;
    }
}
