<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\TtsService;
use Illuminate\Support\Facades\Storage;

$tts = new TtsService();
$text = "Testing Samuel's voice on the server.";
$filename = 'test_samuel.wav';
$path = storage_path('app/public/tts/' . $filename);

echo "Generating to: $path\n";
$success = $tts->generate($text, $path);

if ($success && file_exists($path)) {
    echo "SUCCESS! File size: " . filesize($path) . " bytes\n";
} else {
    echo "FAILED!\n";
}
