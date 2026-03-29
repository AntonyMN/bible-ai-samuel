<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\AiServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateConversationTitle implements ShouldQueue
{
    use Queueable;

    protected $conversationId;
    protected $firstMessage;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct($conversationId, $firstMessage)
    {
        $this->conversationId = $conversationId;
        $this->firstMessage = $firstMessage;
    }

    /**
     * Execute the job.
     */
    public function handle(AiServiceInterface $aiService): void
    {
        $conversation = Conversation::find($this->conversationId);
        
        if (!$conversation) return;

        $messages = [
            ['role' => 'system', 'content' => 'Generate a very short, spiritual title (max 5 words) for a bible chat starting with this message. Return ONLY the title.'],
            ['role' => 'user', 'content' => $this->firstMessage],
        ];

        $response = $aiService->chat($messages);
        $title = $response['message']['content'] ?? 'Divine Reflection';
        
        // Clean up common AI conversational prefixing
        $title = preg_replace('/^(Title:|"|\'|Sure! Here is a title:)\s*/i', '', $title);
        $title = explode("\n", $title)[0];
        $title = trim($title, '" \'.');
        
        if (empty($title)) {
            $title = 'Divine Reflection';
        }

        $conversation->update(['title' => $title]);
    }
}
