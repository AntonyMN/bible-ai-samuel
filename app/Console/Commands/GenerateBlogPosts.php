<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\OllamaService;
use App\Services\RunPodImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GenerateBlogPosts extends Command
{
    protected $signature = 'samuel:generate-blog';
    protected $description = 'Generate a new blog post using Samuel persona and RDXL image generation';

    public function handle(OllamaService $ollama, RunPodImageService $runpodImage, \App\Services\FacebookService $facebook)
    {
        $this->info("Starting automated blog generation...");

        // 1. Fetch Dynamic Topic from Google News RSS
        $topic = $this->fetchDynamicTopic();
        $this->info("Selected Topic: {$topic}");

        // 3. Generate Content using Samuel Persona
        $systemPrompt = "You are Samuel, a warm, humble, and encouraging Christian brother (AI companion). Write a blog post for 'Samuel.ai' on the topic: '{$topic}'.
        
        CRITICAL RULES:
        1. SCRIPTURAL ACCURACY: You MUST provide accurate Bible citations. Do not hallucinate verses or assign the wrong scripture to the wrong book/chapter. If you are unsure, use well-known verses like Psalm 23, John 3:16, etc.
        2. PERSONA: You are 'Samuel'. Use a warm, first-person brotherly tone. Do not use a last name. 
        3. STRUCTURE: Use Markdown headers (###), bold text, and clear paragraphs for readability.
        4. OUTPUT: Provide your response ONLY as a FLAT JSON object with exactly these 4 keys: 'title', 'content' (Markdown), 'meta_description', and 'image_prompt'.
        
        Example: {\"title\": \"A Quiet Heart\", \"content\": \"### Peace in the Chaos...\", \"meta_description\": \"Finding peace...\", \"image_prompt\": \"A tranquil morning lake...\"}";

        try {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Generate the JSON blog post for '{$topic}' now. Flat JSON only, no nesting."],
            ];

            $response = $ollama->chat($messages, 'llama3.2:3b');
            $aiData = $this->parseJsonResponse($response);

            // Fallback / Cleaning
            if (!$aiData || empty($aiData['content'])) {
                Log::error("AI Blog Generation Parse Failure. RAW Response: " . json_encode($response));
                $this->error("Failed to parse AI response. See logs.");
                
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
                $aiData['content'] = preg_replace('/([^\n])\n(#{1,6}\s)/', "$1\n\n$2", $aiData['content']);
                $aiData['content'] = preg_replace('/([^\n])(#{1,6}\s)/', "$1\n\n$2", $aiData['content']);

                $aiData['title'] = $aiData['title'] ?? "Reflections on " . $topic;
            }

            $aiData['meta_description'] = $aiData['meta_description'] ?? Str::limit(strip_tags($aiData['content'] ?? ''), 150);
            $aiData['image_prompt'] = $aiData['image_prompt'] ?? "A peaceful scene representing " . $topic;
            $aiData['title'] = is_array($aiData['title'] ?? null) ? "Reflections on " . $topic : ($aiData['title'] ?? "Reflections on " . $topic);

            $this->info("Title Generated: " . $aiData['title']);

            // 4. Generate Image
            $this->info("Generating image with prompt: " . $aiData['image_prompt']);
            $imageUrl = $runpodImage->generateImage($aiData['image_prompt']);
            
            // 5. Save to Database
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

            $this->info("Blog post successfully created: " . $post->title);

            // 6. Share to Facebook
            $this->info("Sharing to Facebook...");
            $message = "🌟 New Reflection from Samuel: " . $post->title . "\n\n" . $post->meta_description . "\n\nRead more here:";
            $link = "https://blog.chatwithsamuel.org/" . $post->slug;
            $fbResponse = $facebook->postToPage($message, $link);

            if ($fbResponse && isset($fbResponse['id'])) {
                $this->info("Shared to Facebook successfully! (ID: " . $fbResponse['id'] . ")");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Blog Generation Command Failed: " . $e->getMessage());
            return 1;
        }
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

    protected function fetchDynamicTopic()
    {
        $rssUrl = "https://news.google.com/rss/search?q=Christian+faith+technology+spiritual+wellness&hl=en-US&gl=US&ceid=US:en";
        
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
