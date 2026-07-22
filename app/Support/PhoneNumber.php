<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FamilyMember;
use App\Models\User;

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
     * Owner account for Profile WhatsApp (always user id 1).
     */
    public static function primaryUser(): ?User
    {
        return User::query()->whereKey(1)->first();
    }

    /**
     * Owner outbound target for ping, welcome, and budget WhatsApp alerts.
     * Profile phone on user id 1.
     */
    public static function primaryWhatsAppNumber(): ?string
    {
        $user = self::primaryUser();

        if ($user === null) {
            return null;
        }

        return self::normalize(is_string($user->phone) ? $user->phone : null);
    }

    /**
     * Numbers allowed to trigger WhatsApp bot replies / receipt import.
     * User id 1 Profile phone plus Family Members with allowlist enabled.
     *
     * @return list<string>
     */
    public static function allowedWhatsAppSenders(): array
    {
        $numbers = [];

        $primary = self::primaryWhatsAppNumber();

        if ($primary !== null) {
            $numbers[] = $primary;
        }

        $familyPhones = FamilyMember::query()
            ->allowlisted()
            ->pluck('phone')
            ->all();

        foreach ($familyPhones as $phone) {
            $normalized = self::normalize(is_string($phone) ? $phone : null);

            if ($normalized !== null) {
                $numbers[] = $normalized;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Allowlist entries grouped for EvolutionAPI UI.
     *
     * @return array{
     *     primary: list<array{name: string, phone: string}>,
     *     family: list<array{name: string, phone: string}>
     * }
     */
    public static function allowedWhatsAppSenderEntries(): array
    {
        $primary = [];
        $family = [];
        $seen = [];

        $user = self::primaryUser();

        if ($user !== null) {
            $normalized = self::normalize(is_string($user->phone) ? $user->phone : null);

            if ($normalized !== null) {
                $seen[$normalized] = true;
                $primary[] = [
                    'name' => filled($user->name) ? (string) $user->name : 'Primary',
                    'phone' => $normalized,
                ];
            }
        }

        $members = FamilyMember::query()
            ->allowlisted()
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        foreach ($members as $member) {
            $normalized = self::normalize($member->phone);

            if ($normalized === null || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $family[] = [
                'name' => filled($member->name) ? (string) $member->name : 'Family member',
                'phone' => $normalized,
            ];
        }

        return [
            'primary' => $primary,
            'family' => $family,
        ];
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
