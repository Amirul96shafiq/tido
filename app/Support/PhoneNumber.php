<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneNumber
{
    /**
     * Normalize a Malaysian WhatsApp number to digits only (e.g. 60123456789).
     * Accepts +60…, 60…, and leading-0 local forms (012…).
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '60')) {
            return null;
        }

        $length = strlen($digits);

        if ($length < 11 || $length > 13) {
            return null;
        }

        return $digits;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }
}
