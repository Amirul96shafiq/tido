<?php

declare(strict_types=1);

namespace App\Support;

final class SignInWelcomeCopy
{
    public const ENGLISH = 'Welcome Back!';

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
            'MY' => 'Selamat kembali!',
            'ID' => 'Selamat datang kembali!',
            'TH' => 'ยินดีต้อนรับกลับ!',
            'CN' => '欢迎回来!',
            'TW' => '歡迎回來!',
            'HK' => '歡迎回來!',
            'MO' => '歡迎回來!',
            'SG' => '欢迎回来!',
            'JP' => 'おかえりなさい!',
            'KR' => '환영합니다!',
            'SA' => 'مرحبًا بعودتك!',
            'AE' => 'مرحبًا بعودتك!',
            'FR' => 'Bon retour !',
            'DE' => 'Willkommen zurück!',
            'ES' => '¡Bienvenido de nuevo!',
            'PT' => 'Bem-vindo de volta!',
            'BR' => 'Bem-vindo de volta!',
            'IN' => 'वापसी पर स्वागत है!',
            'PH' => 'Maligayang pagbabalik!',
            'VN' => 'Chào mừng trở lại!',
            'TR' => 'Tekrar hoş geldiniz!',
            'IT' => 'Bentornato!',
            'NL' => 'Welkom terug!',
            'PL' => 'Witaj z powrotem!',
            'RU' => 'С возвращением!',
            'UA' => 'З поверненням!',
        ];
    }
}
