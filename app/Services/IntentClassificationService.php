<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class IntentClassificationService
{
    protected $aiService;

    public function __construct(AiServiceInterface $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Classify the intention behind the user message.
     * 
     * @param string $message
     * @return string One of: 'image', 'video', 'factual', 'reflection'
     */
    public function classify($message)
    {
        $prompt = "Classify the intention behind the following user query for a biblical AI assistant named Samuel.
Respond with ONLY ONE word from this list: [image, video, factual, reflection].

- image: User wants to generate, see, or create a visual/spiritual image.
- video: User wants to generate, see, or create a video.
- factual: User is asking for a specific biblical fact (names, dates, family trees, word meanings).
- reflection: User is seeking pastoral care, guidance, reflection, prayer, or a deep theological discussion.

User Query: \"$message\"

Intention:";

        try {
            $response = $this->aiService->chat([
                ['role' => 'user', 'content' => $prompt]
            ], 'gemini-1.5-flash-latest');

            $intention = strtolower(trim($response['content'] ?? 'reflection'));
            
            // Cleanup in case LLM is chatty
            if (str_contains($intention, 'image')) return 'image';
            if (str_contains($intention, 'video')) return 'video';
            if (str_contains($intention, 'factual')) return 'factual';
            
            return 'reflection';
        } catch (\Exception $e) {
            Log::error("Intent classification failed: " . $e->getMessage());
            return 'reflection';
        }
    }
}
