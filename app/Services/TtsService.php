<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TtsService
{
    protected $piperPath;
    protected $modelPath;

    public function __construct()
    {
        $this->piperPath = storage_path('app/piper/piper/piper');
        $this->modelPath = storage_path('app/piper/en_US-hfc_male-medium.onnx');
    }

    /**
     * Generate speech from text and save to a file.
     *
     * @param string $text
     * @param string $outputPath
     * @return bool
     */
    public function generate(string $text, string $outputPath): bool
    {
        try {
            // Pre-process text to add natural pauses at line breaks (User requirement)
            // Add a period to any line that ends without terminal punctuation to force a pause in Piper
            $processedText = preg_replace('/(?<![.!?])\n+/', ". \n", $text);
            
            // Piper expects text via stdin
            $process = Process::input($processedText)
                ->timeout(60)
                ->run([
                    $this->piperPath,
                    '--model', $this->modelPath,
                    '--output_file', $outputPath,
                ]);

            if ($process->failed()) {
                Log::error('Piper TTS failed', [
                    'error' => $process->errorOutput(),
                    'text' => $text,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Piper TTS Exception: ' . $e->getMessage());
            return false;
        }
    }
}
