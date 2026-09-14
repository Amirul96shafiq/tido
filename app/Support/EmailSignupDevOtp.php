<?php

declare(strict_types=1);

namespace App\Support;

final class EmailSignupDevOtp
{
    public static function isEnabled(): bool
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            return false;
        }

        $otp = config('services.email_signup.dev_otp');

        return is_string($otp) && preg_match('/^\d{6}$/', $otp) === 1;
    }

    public static function code(): ?string
    {
        if (! self::isEnabled()) {
            return null;
        }

        $otp = config('services.email_signup.dev_otp');

        return is_string($otp) ? $otp : null;
    }

    public static function isDevAddress(?string $normalizedEmail): bool
    {
        if (! self::isEnabled() || $normalizedEmail === null) {
            return false;
        }

        $addresses = config('services.email_signup.dev_addresses', '');

        if (! is_string($addresses) || $addresses === '') {
            return false;
        }

        foreach (explode(',', $addresses) as $address) {
            if (! is_string($address)) {
                continue;
            }

            if (self::normalizeEmail($address) === $normalizedEmail) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return $normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            ? $normalized
            : null;
    }
}
