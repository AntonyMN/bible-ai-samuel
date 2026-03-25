<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSamuelResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $messages;
    public $model;
    public $conversationId;
    public $userId;
    public $bibleVersion;
    public $citations;
    public $isEmergency;
    public $emergencyType;
    public $isNewDonor;

    /**
     * Create a new job instance.
     */
    public function __construct(array $messages, string $model, string $conversationId, ?string $userId, string $bibleVersion, array $citations, bool $isEmergency = false, string $emergencyType = '', bool $isNewDonor = false)
    {
        $this->messages = $messages;
        $this->model = $model;
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->bibleVersion = $bibleVersion;
        $this->citations = $citations;
        $this->isEmergency = $isEmergency;
        $this->emergencyType = $emergencyType;
        $this->isNewDonor = $isNewDonor;
    }

    /**
     * Execute the job.
     */
    public function handle(OllamaService $ollama): void
    {
        try {
            // 1. Call AI
            $response = $ollama->chat($this->messages, $this->model);
            $aiContent = $response['message']['content'] ?? 'Peace be with you. I am having a moment of silence...';

            // 2. Donor Thank You (Failsafe)
            if ($this->isNewDonor) {
                if (stripos($aiContent, "thank") === false && stripos($aiContent, "donat") === false) {
                     $aiContent = "I want to start by expressing my deepest gratitude for your kind support. Thank you for your donation, it helps me stay online and serve the brothers and sisters. " . $aiContent;
                }
            }

            // 3. Attach Footnotes
            $aiContent = $this->attachSystematicFootnotes($aiContent, $this->bibleVersion);

            // 4. Emergency Failsafe
            if ($this->isEmergency) {
                $resourceInfo = ($this->emergencyType === 'abuse')
                    ? "PLEASE SEEK HELP IMMEDIATELY: Call 1-800-799-SAFE or local emergency services."
                    : "PLEASE SEEK HELP IMMEDIATELY: Call 988 or local emergency services.";
                if (stripos($aiContent, "988") === false && stripos($aiContent, "Hotline") === false) {
                    $aiContent = $resourceInfo . "\n\n" . $aiContent;
                }
            }

            $aiMessage = [
                'role' => 'assistant',
                'content' => $aiContent,
                'citations' => $this->citations,
            ];

            // 5. Save to Database
            $conversation = Conversation::find($this->conversationId);
            if ($conversation) {
                $currentMessages = $conversation->messages;
                $currentMessages[] = $aiMessage;
                $conversation->update(['messages' => $currentMessages]);
            }

            // 6. Broadcast via Reverb
            broadcast(new MessageSent($aiMessage, $this->conversationId));

        } catch (\Exception $e) {
            Log::error("ProcessSamuelResponse failed: " . $e->getMessage());
            broadcast(new MessageSent([
                'role' => 'assistant',
                'content' => "I am sorry, something went wrong. Please reach out again.",
            ], $this->conversationId));
        }
    }

    private function attachSystematicFootnotes($content, $version)
    {
        $pattern = '/((?:[1-3]\s?)?[A-Z][a-z]+\.?)(?:\s+|(?<=[a-z])(?=\d))(?:\s*chapter\s+)?(\d+)(?::(\d+)(?:-(\d+))?)?/i';
        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return $content;
        }

        $pendingQueries = [];
        foreach ($matches as $match) {
            $book = $match[1];
            $chapter = (int) $match[2];
            $verseStart = isset($match[3]) ? (int) $match[3] : 1;
            $verseEnd = isset($match[4]) ? (int) $match[4] : (isset($match[3]) ? $verseStart : 5);
            $pendingQueries[] = ['book' => $book, 'chapter' => $chapter, 'start' => $verseStart, 'end' => $verseEnd];
        }

        $footnotes = [];
        foreach ($pendingQueries as $q) {
            $verses = \App\Models\Verse::where('version', $version)
                ->where('book', 'like', "{$q['book']}%")
                ->where('chapter', $q['chapter'])
                ->whereBetween('verse', [$q['start'], $q['end']])
                ->orderBy('verse')
                ->get();

            if ($verses->count() > 0) {
                $text = $verses->pluck('text')->join(' ');
                $bookName = $verses->first()->book;
                $fullRef = "{$bookName} {$q['chapter']}:{$q['start']}" . ($q['start'] != $q['end'] ? "-{$q['end']}" : "");
                $footnotes[] = "{$fullRef}: {$text} ({$version})";
            }
        }

        if (empty($footnotes)) return $content;
        $footnotes = array_unique($footnotes);
        // Limit to 5 footnotes to avoid payload too large errors
        if (count($footnotes) > 5) {
            $footnotes = array_slice($footnotes, 0, 5);
            $footnotes[] = "...and more.";
        }
        $footer = "\n\n---\n\n**Scriptures Reference:**\n\n- " . implode("\n\n- ", $footnotes) . "\n\n";
        return $content . $footer;
    }
}
