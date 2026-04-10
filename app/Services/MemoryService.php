<?php

namespace App\Services;

use App\Models\Memory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    protected $aiService;

    public function __construct(AiServiceInterface $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Inject active memories into the system prompt.
     */
    public function getInjectedContext($userId)
    {
        $now = now();
        $memories = Memory::where('user_id', $userId)
            ->where('is_completed', '!=', true)
            ->where(function($query) use ($now) {
                $query->whereNull('last_mentioned_at')
                      ->orWhere('last_mentioned_at', '<', $now->subHours(24));
            })
            ->orderBy('importance', 'desc')
            ->limit(5)
            ->get();

        if ($memories->isEmpty()) {
            return "";
        }

        $context = "\n### VITAL USER INFORMATION (Long-Term Memory)\n";
        $context .= "Samuel, you remember these things about the user. Reference them naturally if relevant. Do NOT probe if you already have the details.\n";
        
        foreach ($memories as $memory) {
            $line = "- [" . strtoupper($memory->category) . "] " . $memory->content;
            
            // Highlight if it needs probing
            if ($memory->category === 'events' && $memory->probe_status !== 'completed') {
                $missing = [];
                if (!$memory->occurs_at) $missing[] = "date/time";
                if (!$memory->significance) $missing[] = "significance";
                if (!empty($missing)) {
                    $line .= " (Needs probing for: " . implode(', ', $missing) . ")";
                }
            }

            // Highlight if it's a past event for follow-up
            if ($memory->occurs_at && $memory->occurs_at < now() && $memory->probe_status !== 'followed_up') {
                $line .= " (EVENT PASSED: Ask how it went)";
            }

            $context .= $line . "\n";
        }
        
        return $context . "\n";
    }

    /**
     * Extract new memories from a user message.
     */
    public function extractMemories($userId, $userMessage, $conversationId = null)
    {
        $now = now()->toIso8601String();
        $prompt = "You are a memory extractor for Samuel, a Christian AI companion. " .
            "Extract specific, vital facts about the user's life from the message below. " .
            "Current Time: $now\n\n" .
            "Categories:\n" .
            "- events: Weddings, trips, meetings, etc.\n" .
            "- struggles: Personal challenges, sins, health issues.\n" .
            "- victories: Answered prayers, successes, joys.\n" .
            "- prayer points: Specific things the user wants prayer for.\n" .
            "- knowledge base: Objective facts user wants you to remember (e.g. 'Mother of Moses is Jochebed').\n" .
            "- plans: Future intentions or goals.\n" .
            "- preference: Likes, dislikes, habits.\n\n" .
            "Return a JSON array of objects with:\n" .
            "- content: Concise summary (e.g. 'Attending a wedding').\n" .
            "- category: One of the above.\n" .
            "- importance: 1-5 (5 is critical).\n" .
            "- occurs_at: ISO8601 date if mentioned (resolve 'this Saturday' based on Current Time).\n" .
            "- is_recurring: boolean.\n" .
            "- is_one_off: boolean (true for weddings, false for 'I like tea').\n" .
            "- significance: Why this matters to the user if mentioned.\n\n" .
            "Rules:\n" .
            "- Ignore general conversation or bible talk.\n" .
            "- If a fact is already known but updated, extract the update.\n" .
            "User Message: \"$userMessage\"\n" .
            "JSON Output:";

        try {
            $messages = [['role' => 'user', 'content' => $prompt]];
            // Using a higher model if available for extraction
            $response = $this->aiService->chat($messages, config('services.ollama.model'));
            
            $aiContent = $response['message']['content'] ?? '';
            $jsonStr = $this->cleanJson($aiContent);
            $data = json_decode($jsonStr, true);

            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['content']) && !empty($item['content'])) {
                        // Check for duplicates in the same conversation to avoid spamming
                        $exists = Memory::where('user_id', $userId)
                            ->where('content', 'like', '%' . $item['content'] . '%')
                            ->where('is_completed', false)
                            ->exists();

                        if (!$exists) {
                            Memory::create([
                                'user_id' => $userId,
                                'content' => $item['content'],
                                'category' => $item['category'] ?? 'other',
                                'importance' => $item['importance'] ?? 3,
                                'occurs_at' => isset($item['occurs_at']) ? \Carbon\Carbon::parse($item['occurs_at']) : null,
                                'is_recurring' => $item['is_recurring'] ?? false,
                                'is_one_off' => $item['is_one_off'] ?? true,
                                'significance' => $item['significance'] ?? null,
                                'probe_status' => 'none',
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
            }
        } catch (\Exception $e) {
            Log::warning("Memory extraction failed: " . $e->getMessage());
        }
    }

    /**
     * Update memory metadata after Samuel mentions it in a response.
     */
    public function markAsMentioned($userId, $aiResponse)
    {
        $memories = Memory::where('user_id', $userId)
            ->where('is_completed', false)
            ->get();

        foreach ($memories as $memory) {
            // Check if Samuel mentioned this content in the response
            // Simple keyword check for now
            if (stripos($aiResponse, $memory->content) !== false || 
                ($memory->significance && stripos($aiResponse, $memory->significance) !== false)) {
                
                $memory->increment('mention_count');
                $memory->last_mentioned_at = now();
                
                // If it was a follow-up for a past event, or a knowledge base item, mark it as completed
                if (($memory->occurs_at && $memory->occurs_at < now()) || $memory->category === 'knowledge base') {
                    $memory->probe_status = 'followed_up';
                    if ($memory->is_one_off || $memory->category === 'knowledge base') {
                        $memory->is_completed = true;
                    }
                }

                // If it was a probe for details, and Samuel asked, update status
                if ($memory->probe_status === 'none') {
                    $memory->probe_status = 'probed';
                }

                $memory->save();
            }
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
