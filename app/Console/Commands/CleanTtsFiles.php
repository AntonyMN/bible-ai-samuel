<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanTtsFiles extends Command
{
    protected $signature = 'tts:clean';
    protected $description = 'Clean up TTS audio files older than 30 minutes';

    public function handle()
    {
        $disk = Storage::disk('public');
        $directory = 'tts';
        
        if (!$disk->exists($directory)) {
            $this->info("Directory {$directory} on public disk does not exist.");
            return;
        }

        $files = $disk->files($directory);
        $now = Carbon::now();
        $count = 0;

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));
            
            // Delete if older than 2 minutes
            if ($now->diffInMinutes($lastModified) >= 2) {
                $disk->delete($file);
                $count++;
            }
        }

        $this->info("Deleted {$count} old TTS files.");
    }
}
