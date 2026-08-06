<?php

declare(strict_types=1);

namespace App\Support;

final class EvolutionCredential
{
    public const MINIMUM_LENGTH = 32;

    public static function isValid(string $credential): bool
    {
        $credential = trim($credential);

        if ($credential === '' || strlen($credential) < self::MINIMUM_LENGTH) {
            return false;
        }

        if (str_starts_with($credential, '<') && str_ends_with($credential, '>')) {
            return false;
        }

        return ! in_array(strtolower($credential), [
            'change-me',
            'changeme',
            'replace-me',
            'replace-this',
            'your-api-key',
            'your-secret-key',
            'your-webhook-secret',
        ], true);
    }

    public static function areDistinct(string $first, string $second): bool
    {
        if (! self::isValid($first) || ! self::isValid($second)) {
            return false;
        }

        return ! hash_equals(trim($first), trim($second));
    }
}
