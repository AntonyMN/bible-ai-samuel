<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanupOldAudio extends Command
{
    protected $signature = 'samuel:cleanup-audio';
    protected $description = 'Delete audio files older than 72 hours';

    public function handle()
    {
        $audioPath = public_path('audio');
        
        if (!File::exists($audioPath)) {
            $this->info("Audio directory does not exist.");
            return 0;
        }

        $files = File::files($audioPath);
        $count = 0;
        $now = Carbon::now();

        foreach ($files as $file) {
            // Check if file is older than 72 hours
            if ($now->diffInHours(Carbon::createFromTimestamp($file->getMTime())) >= 72) {
                File::delete($file);
                $this->info("Deleted: " . $file->getFilename());
                $count++;
            }
        }

        $this->info("Cleanup complete. Deleted {$count} files.");
        return 0;
    }
}
