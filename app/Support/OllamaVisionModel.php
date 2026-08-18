<?php

declare(strict_types=1);

namespace App\Support;

final class OllamaVisionModel
{
    /**
     * @var list<string>
     */
    private const KNOWN_VISION_FAMILIES = [
        'qwen25vl',
        'qwen2.5vl',
        'llava',
        'minicpm-v',
        'moondream',
        'bakllava',
        'llama3.2-vision',
    ];

    public static function isLikelyVisionModel(string $modelName, string $family = ''): bool
    {
        $haystack = strtolower($modelName.' '.$family);

        foreach (self::KNOWN_VISION_FAMILIES as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return str_contains($haystack, 'vl') || str_contains($haystack, 'vision');
    }
}
