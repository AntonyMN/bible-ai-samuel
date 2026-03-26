<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\AiServiceInterface;
use App\Services\OllamaService; // Keep for legacy if needed, but we'll use AiServiceInterface
use App\Services\RunPodImageService;
use App\Services\FacebookService;
use App\Services\TtsService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerateBlogPosts extends Command
{
    protected $signature = 'samuel:generate-blog {--jitter=0 : Random delay in minutes} {--evening : Prioritize evening themes}';
    protected $description = 'Generate a new blog post using Samuel persona and RDXL image generation';

    public function handle(AiServiceInterface $aiService, RunPodImageService $runpodImage, FacebookService $facebook, TtsService $tts)
    {
        // Handle Jitter
        if ($jitterMax = (int) $this->option('jitter')) {
            $delay = rand(0, $jitterMax);
            $this->info("Jitter enabled (max {$jitterMax}m). Sleeping for {$delay} minutes...");
            sleep($delay * 60);
        }

        $this->info("Starting automated blog generation...");

        // 1. Fetch Dynamic Topic from Google News RSS
        $topic = $this->fetchDynamicTopic($this->option('evening'));
        $this->info("Selected Topic: {$topic}");

        // 3. Generate Content using Samuel Persona (BSB Default)
        $systemPrompt = "You are Samuel, a warm, humble, and encouraging Christian brother (AI companion). Write a personal, brotherly journal-style reflection for 'Samuel.ai' on the topic: '{$topic}'.
        
        IDENTITY & TONE:
        1. You are an individual brother reflecting on news and life. You are NOT an organization, company, or news agency.
        2. Use a warm, first-person singular perspective ('I', 'me', 'my'). 
        3. STRICT RULE: NEVER use 'we', 'our', or 'us' when referring to events, institutions, or news (e.g. do not say 'we are breaking ground', say 'I was moved to hear that...').
        4. Opening: Start with a personal, brotherly touch (e.g., 'I was just reading about...', 'My dear brothers and sisters, I wanted to share...').
        5. Mission: Share how this topic moved your heart and what scriptures God brought to mind.

        CRITICAL RULES:
        1. SCRIPTURAL ACCURACY: You MUST provide accurate Bible citations using the **BSB (Berean Standard Bible)** version. Do not hallucinate verses.
        2. STRUCTURE: Use Markdown headers (###), bold text, and clear paragraphs.
        3. OUTPUT: Provide your response ONLY as a FLAT JSON object with: 'title', 'content' (Markdown), 'meta_description', and 'image_prompt'.";

        try {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Generate the JSON blog post for '{$topic}' now. Flat JSON only."],
            ];

            $response = $aiService->chat($messages, 'llama3.2:3b');
            $aiData = $this->parseJsonResponse($response);

            // Fallback / Cleaning
            if (!$aiData || empty($aiData['content'])) {
                Log::error("AI Blog Generation Parse Failure. RAW Response: " . json_encode($response));
                
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

            // 5. Inject Verbatim Scriptures (Footnotes)
            $aiData['content'] = $this->attachSystematicFootnotes($aiData['content'], 'BSB');

            $aiData['meta_description'] = $aiData['meta_description'] ?? Str::limit(strip_tags($aiData['content']), 150);
            
            // Ensure title and image_prompt are strings (LLMs sometimes output arrays)
            $aiData['title'] = is_array($aiData['title'] ?? null) ? implode(' ', $aiData['title']) : ($aiData['title'] ?? "Reflections on " . $topic);
            $aiData['image_prompt'] = is_array($aiData['image_prompt'] ?? null) ? implode(' ', $aiData['image_prompt']) : ($aiData['image_prompt'] ?? "A peaceful scene representing " . $topic);

            $this->info("Title Generated: " . $aiData['title']);

            // 6. Generate Image
            $this->info("Generating image...");
            $imageUrl = $runpodImage->generateImage($aiData['image_prompt']);
            
            // 7. Save to Database
            $post = Post::create([
                'title' => $aiData['title'],
                'slug' => Str::slug($aiData['title']) . '-' . rand(100, 999), 
                'content' => $aiData['content'],
                'image_url' => $imageUrl,
                'topic' => $topic,
                'status' => 'published',
                'published_at' => now(),
                'meta_description' => $aiData['meta_description'],
            ]);

            // 8. Generate Voiceover (TTS)
            $this->info("Generating voiceover...");
            $cleanText = strip_tags($aiData['content']);
            $cleanText = preg_replace('/###\s+/', '', $cleanText); // Remove markdown headers
            $cleanText = str_replace('*', '', $cleanText); // Remove bold/italic markers for TTS
            $audioFileName = "blog_" . $post->id . ".wav";
            $audioPath = public_path("audio/" . $audioFileName);
            
            if (!file_exists(public_path('audio'))) {
                mkdir(public_path('audio'), 0755, true);
            }

            if ($tts->generate($cleanText, $audioPath)) {
                $post->update(['audio_url' => "/audio/" . $audioFileName]);
                $this->info("Voiceover generated: " . $audioFileName);
            }

            $this->info("Blog post successfully created: " . $post->title);

            // 9. Share to Facebook
            $this->info("Sharing to Facebook...");
            $message = "🌟 New Reflection from Samuel: " . $post->title . "\n\n" . $post->meta_description . "\n\nRead more and listen here: " . "https://blog.chatwithsamuel.org/" . $post->slug;
            
            // Ensure absolute URL for Facebook
            $absoluteImageUrl = $post->image_url;
            if (str_starts_with($absoluteImageUrl, '/')) {
                $absoluteImageUrl = "https://blog.chatwithsamuel.org" . $absoluteImageUrl;
            }

            $fbResponse = $facebook->postPhoto($message, $absoluteImageUrl);

            if ($fbResponse && isset($fbResponse['id'])) {
                $this->info("Shared to Facebook successfully! (ID: " . $fbResponse['id'] . ")");
            } else {
                $this->sendFailureNotification("Blog post created but Facebook sharing failed. Title: " . $post->title);
            }

            return 0;

        } catch (\Exception $e) {
            $errorMsg = "Samuel AI Blog Generation Failed: " . $e->getMessage();
            $this->error($errorMsg);
            Log::error($errorMsg);
            $this->sendFailureNotification($errorMsg);
            return 1;
        }
    }

    private function sendFailureNotification($message)
    {
        try {
            Mail::raw($message, function ($mail) {
                $mail->to('antonymuriuki7@gmail.com')
                     ->subject('Samuel AI: Blog Generation/Social Failure Alert');
            });
            $this->info("Failure notification sent to antonymuriuki7@gmail.com");
        } catch (\Exception $e) {
            Log::error("Failed to send notification email: " . $e->getMessage());
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

            $verses = \App\Models\Verse::where('version', $version)
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

    protected function parseJsonResponse($response)
    {
        $content = $response['message']['content'] ?? '';
        
        // 1. Standard approach
        $clean = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));
        $data = json_decode($clean, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        // 2. Aggressive cleaning for unescaped newlines/quotes
        // This is a common issue with smaller Llama models
        try {
            // Attempt to fix common JSON errors before parsing
            $clean = preg_replace('/(?<!\\\\)\n/', '\\n', $clean); // Fix unescaped newlines
            return json_decode($clean, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function fetchDynamicTopic($evening = false)
    {
        $search = $evening ? "Christian+faith+Peace+Sleep+winding+down" : "Christian+faith+technology+spiritual+wellness";
        $rssUrl = "https://news.google.com/rss/search?q=" . urlencode($search) . "&hl=en-US&gl=US&ceid=US:en";
        
        try {
            $rss = simplexml_load_file($rssUrl);
            if ($rss && isset($rss->channel->item)) {
                foreach ($rss->channel->item as $item) {
                    $title = (string) $item->title;
                    // Strip the source (e.g. "Headline - Source Name")
                    $title = preg_replace('/ - [^-]+$/', '', $title);

                    // Check if we've already used this topic
                    if (!Post::where('topic', $title)->exists()) {
                        return $title;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("RSS Fetch Failed: " . $e->getMessage());
        }

        // Fallback to curated topics if RSS fails or no new topics
        $fallbackTopics = [
            "Merging Faith and Holistic Wellness",
            "The search for meaning in a post-truth world",
            "Spiritual awakening among Gen Z",
            "Emotional healing and nervous system regulation through faith",
            "Authentic community vs algorithm-driven engagement",
            "Finding peace in an era of rapid technological change",
            "The impact of AI on prayer and meditation",
            "Building digital bridges for spiritual growth",
        ];

        return $fallbackTopics[array_rand($fallbackTopics)];
    }
}
