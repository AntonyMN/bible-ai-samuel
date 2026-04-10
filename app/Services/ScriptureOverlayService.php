<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScriptureOverlayService
{
    protected $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf';

    /**
     * Overlay scripture text on an image
     * 
     * @param string $imagePath Relative path in public storage
     * @param string $verse Text to overlay
     * @param string $reference Scripture reference
     * @return string|null New image path or null on failure
     */
    public function overlay($imagePath, $verse, $reference)
    {
        if (!extension_loaded('gd')) {
            Log::warning("GD extension not loaded. Skipping scripture overlay.");
            return $imagePath;
        }

        try {
            $fullPath = storage_path('app/public/' . str_replace('/storage/', '', $imagePath));
            if (!file_exists($fullPath)) {
                Log::error("Overlay source image not found: " . $fullPath);
                return $imagePath;
            }

            $image = imagecreatefrompng($fullPath);
            if (!$image) {
                // Try jpeg if png fails
                $image = imagecreatefromjpeg($fullPath);
            }

            if (!$image) {
                Log::error("Could not create image resource from: " . $fullPath);
                return $imagePath;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            // Colors
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 60);

            // Font Settings
            $fontSize = 28;
            $padding = 60;
            $maxTextWidth = $width - ($padding * 2);

            // Wrap text
            $wrappedText = $this->wrapText($fontSize, 0, $this->fontPath, $verse, $maxTextWidth);
            $refText = "\n— " . $reference;
            $fullText = $wrappedText . $refText;

            // Calculate text height for vertical alignment (bottom center)
            $bbox = imagettfbbox($fontSize, 0, $this->fontPath, $fullText);
            $textHeight = abs($bbox[7] - $bbox[1]);
            
            $x = $padding;
            $y = $height - $textHeight - $padding;

            // Draw a subtle dark gradient at the bottom for readability
            for ($i = 0; $i < ($textHeight + $padding * 1.5); $i++) {
                $alpha = 127 - (int)(($i / ($textHeight + $padding * 1.5)) * 60);
                if ($alpha < 0) $alpha = 0;
                $gradColor = imagecolorallocatealpha($image, 0, 0, 0, $alpha);
                imageline($image, 0, $height - $i, $width, $height - $i, $gradColor);
            }

            // Draw shadow for each line
            $lines = explode("\n", $fullText);
            $currentY = $y;
            foreach ($lines as $line) {
                $lineBbox = imagettfbbox($fontSize, 0, $this->fontPath, $line);
                $lineWidth = abs($lineBbox[4] - $lineBbox[0]);
                $centeredX = ($width - $lineWidth) / 2;

                // Shadow
                imagettftext($image, $fontSize, 0, $centeredX + 2, $currentY + 2, $shadowColor, $this->fontPath, $line);
                // Main Text
                imagettftext($image, $fontSize, 0, $centeredX, $currentY, $white, $this->fontPath, $line);
                
                $currentY += $fontSize * 1.6;
            }

            // Save the new image
            $newFilename = 'spiritual_images/' . uniqid() . '.png';
            if (!Storage::disk('public')->exists('spiritual_images')) {
                Storage::disk('public')->makeDirectory('spiritual_images');
            }

            $savePath = storage_path('app/public/' . $newFilename);
            imagepng($image, $savePath);
            imagedestroy($image);

            return Storage::url($newFilename);

        } catch (\Exception $e) {
            Log::error("Scripture Overlay failed: " . $e->getMessage());
            return $imagePath;
        }
    }

    /**
     * Helper to wrap text for GD
     */
    private function wrapText($fontSize, $angle, $fontFace, $string, $width)
    {
        $ret = "";
        $arr = explode(' ', $string);
        foreach ($arr as $word) {
            $testString = $ret . ' ' . $word;
            $testbox = imagettfbbox($fontSize, $angle, $fontFace, $testString);
            if (abs($testbox[4] - $testbox[0]) < $width) {
                $ret = $testString;
            } else {
                $ret .= "\n" . $word;
            }
        }
        return trim($ret);
    }
}
