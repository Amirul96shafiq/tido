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

    /**
     * Parse a comma/space/semicolon-separated list of Malaysian numbers.
     *
     * @return list<string>
     */
    public static function parseList(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $trimmed) ?: [];
        $numbers = [];

        foreach ($parts as $part) {
            $normalized = self::normalize($part);

            if ($normalized !== null) {
                $numbers[] = $normalized;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Numbers allowed to trigger WhatsApp bot replies / receipt import.
     * Primary PERSONAL_WHATSAPP_NUMBER plus PERSONAL_WHATSAPP_EXTRA_NUMBERS.
     *
     * @return list<string>
     */
    public static function allowedWhatsAppSenders(): array
    {
        $numbers = [];

        $primary = self::normalize(
            is_string(config('services.evolution.personal_number'))
                ? config('services.evolution.personal_number')
                : null,
        );

        if ($primary !== null) {
            $numbers[] = $primary;
        }

        $extrasRaw = config('services.evolution.personal_extra_numbers');
        $extras = self::parseList(is_string($extrasRaw) ? $extrasRaw : null);

        return array_values(array_unique([...$numbers, ...$extras]));
    }

    public static function isAllowedWhatsAppSender(string $senderNumber): bool
    {
        $normalized = self::normalize($senderNumber);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::allowedWhatsAppSenders(), true);
    }
}
