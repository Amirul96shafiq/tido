<?php

declare(strict_types=1);

namespace App\Support;

final class RestoreToken
{
    public const SELECTOR_LENGTH = 16;

    public const SECRET_LENGTH = 32;

    public const PLAIN_LENGTH = 49;

    /**
     * Constant-time work for unknown selectors (never used for storage).
     */
    public const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /**
     * @return non-empty-string
     */
    public static function generate(): string
    {
        $selector = bin2hex(random_bytes(self::SELECTOR_LENGTH / 2));
        $secret = bin2hex(random_bytes(self::SECRET_LENGTH / 2));

        return $selector.'.'.$secret;
    }

    /**
     * @return array{selector: non-empty-string, secret: non-empty-string}|null
     */
    public static function parse(string $plainToken): ?array
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '' || strlen($plainToken) !== self::PLAIN_LENGTH) {
            return null;
        }

        $parts = explode('.', $plainToken, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$selector, $secret] = $parts;

        if (! self::isHex($selector, self::SELECTOR_LENGTH)
            || ! self::isHex($secret, self::SECRET_LENGTH)) {
            return null;
        }

        return [
            'selector' => $selector,
            'secret' => $secret,
        ];
    }

    public static function isValidFormat(string $plainToken): bool
    {
        return self::parse($plainToken) !== null;
    }

    private static function isHex(string $value, int $length): bool
    {
        return strlen($value) === $length
            && ctype_xdigit($value);
    }
}
