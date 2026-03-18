<?php

namespace App\Http\Controllers;

use App\Services\TtsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TtsController extends Controller
{
    protected $tts;

    public function __construct(TtsService $tts)
    {
        $this->tts = $tts;
    }

    /**
     * Generate TTS audio for the given text.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $text = $request->input('text');
        
        // Clean text for caching (strip markdown, etc. to ensure same text = same file)
        $cleanText = strip_tags($text);
        $filename = sha1($cleanText) . '.wav';
        $disk = Storage::disk('public');
        $directory = 'tts';
        $relativeFilepath = $directory . '/' . $filename;
        $absoluteFilepath = storage_path('app/public/' . $relativeFilepath);

        // Ensure directory exists
        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        // Check cache
        if (!$disk->exists($relativeFilepath)) {
            $success = $this->tts->generate($text, $absoluteFilepath);
            
            if (!$success) {
                return response()->json(['message' => 'Failed to generate speech'], 500);
            }
        }

        // Return the public URL
        // Ensure you have run 'php artisan storage:link'
        $url = url('storage/tts/' . $filename);

        return response()->json([
            'url' => $url,
            'filename' => $filename,
        ]);
    }
}
