<?php

declare(strict_types=1);

namespace App\Support;

final class SignupGreetingCopy
{
    public const ENGLISH = 'Hello!';

    public static function localizedForCountry(?string $countryCode): string
    {
        if ($countryCode === null || $countryCode === '') {
            return self::ENGLISH;
        }

        return self::map()[strtoupper($countryCode)] ?? self::ENGLISH;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function typewriterPhrases(?string $countryCode): ?array
    {
        $localized = self::localizedForCountry($countryCode);

        if ($localized === self::ENGLISH) {
            return null;
        }

        return [self::ENGLISH, $localized];
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        return [
            'MY' => 'Hai!',
            'ID' => 'Halo!',
            'TH' => 'สวัสดี!',
            'CN' => '你好!',
            'TW' => '你好!',
            'HK' => '你好!',
            'MO' => '你好!',
            'SG' => '你好!',
            'JP' => 'こんにちは!',
            'KR' => '안녕하세요!',
            'SA' => 'مرحبًا!',
            'AE' => 'مرحبًا!',
            'FR' => 'Bonjour!',
            'DE' => 'Hallo!',
            'ES' => '¡Hola!',
            'PT' => 'Olá!',
            'BR' => 'Olá!',
            'IN' => 'नमस्ते!',
            'PH' => 'Kamusta!',
            'VN' => 'Xin chào!',
            'TR' => 'Merhaba!',
            'IT' => 'Ciao!',
            'NL' => 'Hallo!',
            'PL' => 'Cześć!',
            'RU' => 'Привет!',
            'UA' => 'Привіт!',
        ];
    }
}
