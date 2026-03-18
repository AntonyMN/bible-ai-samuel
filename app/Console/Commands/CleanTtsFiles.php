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
        $directory = 'public/tts';
        
        if (!Storage::exists($directory)) {
            $this->info("Directory {$directory} does not exist.");
            return;
        }

        $files = Storage::files($directory);
        $now = Carbon::now();
        $count = 0;

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));
            
            // Delete if older than 30 minutes
            if ($now->diffInMinutes($lastModified) > 30) {
                Storage::delete($file);
                $count++;
            }
        }

        $this->info("Deleted {$count} old TTS files.");
    }
}
