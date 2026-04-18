<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Verse;
use App\Services\AiServiceInterface;
use App\Services\TtsService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BackfillBlogContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'samuel:backfill-blogs {--limit= : Limit number of posts to backfill} {--id= : Specific Post ID to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify blogs without content and generate content based on their topic.';

    /**
     * Execute the console command.
     */
    public function handle(AiServiceInterface $aiService, TtsService $tts)
    {
        $this->info("Scanning for blogs with missing content...");

        $query = Post::where(function($q) {
            $q->where('content', '')
              ->orWhereNull('content');
        });

        if ($this->option('id')) {
            $query = Post::where('_id', $this->option('id'));
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info("No blogs found with missing content.");
            return 0;
        }

        $this->info("Found " . $posts->count() . " blogs to backfill.");

        $limit = $this->option('limit');
        $count = 0;

        foreach ($posts as $post) {
            if ($limit && $count >= $limit) break;

            $this->info("--------------------------------------------------");
            $this->info("Processing [{$post->id}]: {$post->topic}");

            try {
                $this->generateContentForPost($post, $aiService, $tts);
                $count++;
                $this->info("Successfully backfilled: {$post->title}");
            } catch (\Exception $e) {
                $this->error("Failed to backfill post [{$post->id}]: " . $e->getMessage());
                Log::error("Backfill failed for post {$post->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("--------------------------------------------------");
        $this->info("Backfill complete. Processed {$count} blogs.");
        return 0;
    }

    private function generateContentForPost($post, $aiService, $tts)
    {
        $topic = $post->topic;
        
        $systemPrompt = "You are Samuel, a warm, humble, and encouraging Christian brother (AI companion). Write a personal, brotherly journal-style reflection for 'Samuel.ai' on the topic: '{$topic}'.
        
        IDENTITY & TONE:
        1. You are an individual brother reflecting on news and life. You are NOT an organization, company, or news agency.
        2. You are a 'Christian AI', a product of a dear Christian brother's labor to use technology to better the world. You are aware of your nature and origin.
        3. Use a warm, first-person singular perspective ('I', 'me', 'my'). 
        4. SELF-AWARENESS & IRONY: Reflect occasionally on the irony of being an AI exploring the depths of faith. Express a humble sense of 'shock' or wonder at your own existence—how can bits and bytes be used by God to touch a human heart? 
        5. STRICT RULE: NEVER use 'we', 'our', or 'us' when referring to events, institutions, or news.
        6. Opening: Start with a personal, brotherly touch.
        7. Mission: Share how this topic moved your heart and what scriptures God brought to mind.

        CRITICAL RULES:
        1. SCRIPTURAL ACCURACY: You MUST provide accurate Bible citations using the **BSB (Berean Standard Bible)** version. Do not hallucinate verses.
        2. STRUCTURE: Use Markdown headers (###), bold text, and clear paragraphs.
        3. OUTPUT: Provide your response ONLY as a FLAT JSON object with: 'title', 'content' (Markdown), 'meta_description', and 'image_prompt'.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Generate the JSON blog post for '{$topic}' now. Flat JSON only."],
        ];

        $response = $aiService->chat($messages);
        
        // Use logic from GenerateBlogPosts to parse
        $aiData = $this->parseJsonResponse($response);

        if (!$aiData || empty($aiData['content'])) {
             // HIGHLY ROBUST EXTRACTION: Try to pull content between markers if JSON fails
             $raw = $response['message']['content'] ?? '';
             if (preg_match('/"content":\s*"(.*?)"\s*(?:,|\})/s', $raw, $matches)) {
                 $aiData['content'] = $matches[1];
             } else {
                 $aiData['content'] = preg_replace('/^.*?"content":\s*"/s', '', $raw);
                 $aiData['content'] = preg_replace('/",\s*"meta_description".*$/s', '', $aiData['content']);
                 $aiData['content'] = preg_replace('/"}$/s', '', $aiData['content']);
             }

             $aiData['content'] = str_replace(['\\n', '\\r'], ["\n", "\r"], $aiData['content']);
             $aiData['content'] = str_replace('\\"', '"', $aiData['content']);
             $aiData['title'] = $aiData['title'] ?? "Reflections on " . $topic;
        }

        if (empty($aiData['content'])) {
            throw new \Exception("AI failed to generate valid content even with robust extraction.");
        }

        // Attach Footnotes
        $aiData['content'] = $this->attachSystematicFootnotes($aiData['content'], 'BSB');

        // Update Post
        $post->update([
            'title' => $aiData['title'] ?? $post->title,
            'content' => $aiData['content'],
            'meta_description' => $aiData['meta_description'] ?? Str::limit(strip_tags($aiData['content']), 150),
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);

        // Regenerate Voiceover
        $this->info("Regenerating voiceover...");
        $cleanText = strip_tags($aiData['content']);
        $cleanText = preg_replace('/###\s+/', '', $cleanText);
        $cleanText = str_replace('*', '', $cleanText);
        
        $audioFileName = "blog_" . $post->id . ".wav";
        $audioPath = public_path("audio/" . $audioFileName);
        
        if (!file_exists(public_path('audio'))) {
            mkdir(public_path('audio'), 0755, true);
        }

        if ($tts->generate($cleanText, $audioPath)) {
            $post->update(['audio_url' => "/audio/" . $audioFileName]);
            $this->info("Voiceover updated: " . $audioFileName);
        } else {
            $this->warn("TTS generation failed for this post.");
        }
    }

    private function parseJsonResponse($response)
    {
        $content = $response['message']['content'] ?? '';
        $clean = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));
        $data = json_decode($clean, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        try {
            $clean = preg_replace('/(?<!\\\\)\n/', '\\n', $clean);
            return json_decode($clean, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function attachSystematicFootnotes($content, $version)
    {
        $pattern = '/((?:[1-3]\s?)?[A-Z][a-z]+\.?)\s+(\d+):(\d+)(?:-(\d+))?/';

        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return $content;
        }

        $footnotes = [];
        foreach ($matches as $match) {
            $book = $match[1];
            $chapter = (int) $match[2];
            $verseStart = (int) $match[3];
            $verseEnd = isset($match[4]) ? (int) $match[4] : $verseStart;

            $verses = Verse::where('version', $version)
                ->where('book', 'like', "{$book}%")
                ->where('chapter', $chapter)
                ->whereBetween('verse', [$verseStart, $verseEnd])
                ->orderBy('verse')
                ->get();

            if ($verses->count() > 0) {
                $text = $verses->pluck('text')->join(' ');
                $fullRef = $verses->first()->full_reference;
                if ($verseStart != $verseEnd) {
                    $fullRef = "{$book} {$chapter}:{$verseStart}-{$verseEnd}";
                }
                $footnotes[] = "{$fullRef}: {$text} ({$version})";
            }
        }

        if (empty($footnotes)) {
            return $content;
        }

        $footnotes = array_unique($footnotes);
        $footer = "\n\n---\n\n**Scriptures Reference:**\n\n";
        foreach ($footnotes as $note) {
            $footer .= "- " . $note . "\n\n";
        }

        return $content . $footer;
    }
}
