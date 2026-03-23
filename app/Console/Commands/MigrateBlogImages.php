<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateBlogImages extends Command
{
    protected $signature = 'samuel:migrate-images';
    protected $description = 'Migrate base64 images in blog posts to the filesystem';

    public function handle()
    {
        $this->info("Starting image migration...");

        // Find posts with base64 images
        $posts = Post::where('image_url', 'like', 'data:image%')->get();
        
        // Also find posts that might just be long strings (without the data:image prefix)
        $allPosts = Post::all();
        $potentialPosts = $allPosts->filter(function($post) use ($posts) {
            return !$posts->contains('id', $post->id) && strlen($post->image_url) > 1000;
        });
        
        $targetPosts = $posts->merge($potentialPosts);

        if ($targetPosts->isEmpty()) {
            $this->info("No posts with base64 images found.");
            return 0;
        }

        $this->info("Found " . $targetPosts->count() . " posts to migrate.");

        $bar = $this->output->createProgressBar($targetPosts->count());
        $bar->start();

        foreach ($targetPosts as $post) {
            try {
                $base64String = $post->image_url;
                
                // Clean up the string if it has the data:image prefix
                if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
                    $base64String = substr($base64String, strpos($base64String, ',') + 1);
                    $extension = strtolower($type[1]);
                } else {
                    $extension = 'png'; // Default
                }

                $imageData = base64_decode($base64String);
                
                if (!$imageData) {
                    $this->error("\nFailed to decode image for post: " . $post->id);
                    $bar->advance();
                    continue;
                }

                $filename = 'blog_images/' . Str::random(40) . '.' . $extension;
                
                // Ensure directory exists
                if (!Storage::disk('public')->exists('blog_images')) {
                    Storage::disk('public')->makeDirectory('blog_images');
                }

                Storage::disk('public')->put($filename, $imageData);
                
                $newUrl = Storage::url($filename);
                
                $post->update(['image_url' => $newUrl]);
                
            } catch (\Exception $e) {
                $this->error("\nError migrating post " . $post->id . ": " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nMigration complete!");

        return 0;
    }
}
