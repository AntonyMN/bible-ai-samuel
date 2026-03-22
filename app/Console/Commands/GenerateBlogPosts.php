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

    public function handle(OllamaService $ollama, RunPodImageService $runpodImage)
    {
        $this->info("Starting automated blog generation...");

        // 1. Trending Topics (Fetched from my search as an agent)
        $topics = [
            "Merging Faith and Holistic Wellness",
            "The search for meaning in a post-truth world",
            "Spiritual awakening among Gen Z",
            "Emotional healing and nervous system regulation through faith",
            "Authentic community vs algorithm-driven engagement",
            "Finding peace in an era of rapid technological change",
        ];

        // 2. Select a topic (random or could be smarter)
        $topic = $topics[array_rand($topics)];
        $this->info("Selected Topic: {$topic}");

        // 3. Generate Content using Samuel Persona
        $systemPrompt = "You are Samuel, a warm, empathetic Christian brother. Write a blog post for 'Samuel.ai' about: '{$topic}'.
        
        Provide your response as a FLAT JSON object with exactly these 4 keys:
        1. 'title': A short, brotherly title.
        2. 'content': The Markdown content with scriptural insights.
        3. 'meta_description': 150-char SEO summary.
        4. 'image_prompt': A single sentence describing a peaceful, artistic, or natural scene related to the topic (no text, no faces).
        
        Example: {\"title\": \"...\", \"content\": \"...\", \"meta_description\": \"...\", \"image_prompt\": \"...\"}";

        try {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Generate the JSON blog post for '{$topic}' now. Flat JSON only, no nesting."],
            ];

            $response = $ollama->chat($messages, 'llama3.2:3b');
            $aiData = $this->parseJsonResponse($response);

            // Fallback / Cleaning
            if (!$aiData || !isset($aiData['title'])) {
                Log::error("AI Blog Generation Parse Failure. RAW Response: " . json_encode($response));
                $this->error("Failed to parse AI response. See logs.");
                // Try one more time with a very simple recovery
                if (is_string($response['message']['content'] ?? null)) {
                    $this->info("Attempting simple string recovery...");
                    $aiData['content'] = $response['message']['content'];
                    $aiData['title'] = "Peace in the Digital Age: A Reflection";
                } else {
                    return 1;
                }
            }

            $aiData['meta_description'] = $aiData['meta_description'] ?? Str::limit(strip_tags($aiData['content'] ?? ''), 150);
            $aiData['image_prompt'] = $aiData['image_prompt'] ?? "A peaceful scene representing " . $topic;
            $aiData['title'] = is_array($aiData['title']) ? "Reflections on " . $topic : $aiData['title'];

            $this->info("Title Generated: " . $aiData['title']);

            // 4. Generate Image
            $this->info("Generating image with prompt: " . $aiData['image_prompt']);
            $imageUrl = $runpodImage->generateImage($aiData['image_prompt']);
            $this->info("Image generated: {$imageUrl}");

            // 5. Save to Database
            $post = Post::create([
                'title' => $aiData['title'],
                'slug' => Str::slug($aiData['title']),
                'content' => $aiData['content'],
                'image_url' => $imageUrl,
                'topic' => $topic,
                'status' => 'published',
                'published_at' => now(),
                'meta_description' => $aiData['meta_description'],
            ]);

            $this->info("Blog post successfully created: " . $post->title);
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
        // Strip potential markdown blocks
        $content = preg_replace('/^```json\s*|\s*```$/i', '', $content);
        return json_decode(trim($content), true);
    }
}
