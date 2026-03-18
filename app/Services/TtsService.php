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
        $this->piperPath = '/home/anto/piper/piper/piper';
        $this->modelPath = '/home/anto/piper/en_US-hfc_male-medium.onnx';
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
            // Piper expects text via stdin
            $process = Process::input($text)
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
