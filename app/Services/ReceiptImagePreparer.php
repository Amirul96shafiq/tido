<?php

declare(strict_types=1);

namespace App\Services;

class ReceiptImagePreparer
{
    /**
     * Encode a receipt image for the Ollama vision prompt, downscaling oversized uploads first.
     *
     * Qwen2.5-VL spends roughly one token per 28x28 pixel block, so a full-resolution phone
     * photo can consume the entire context window and leave no budget for the JSON answer.
     * WhatsApp receipts arrive pre-compressed; Filament uploads do not.
     */
    public function toBase64(string $imageContents): string
    {
        $maxDimension = (int) config('services.ollama.max_image_dimension');

        if ($maxDimension <= 0) {
            return base64_encode($imageContents);
        }

        $size = @getimagesizefromstring($imageContents);

        if ($size === false) {
            return base64_encode($imageContents);
        }

        [$width, $height] = $size;

        if (max($width, $height) <= $maxDimension) {
            return base64_encode($imageContents);
        }

        $source = @imagecreatefromstring($imageContents);

        if ($source === false) {
            return base64_encode($imageContents);
        }

        try {
            $scale = $maxDimension / max($width, $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefilledrectangle(
                $canvas,
                0,
                0,
                $targetWidth,
                $targetHeight,
                (int) imagecolorallocate($canvas, 255, 255, 255)
            );
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($canvas, null, 90);
            $downscaled = (string) ob_get_clean();
            imagedestroy($canvas);

            return base64_encode($downscaled !== '' ? $downscaled : $imageContents);
        } finally {
            imagedestroy($source);
        }
    }
}
