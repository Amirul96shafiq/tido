<?php

declare(strict_types=1);

namespace App\Support;

final class WhatsAppLoginDevOtp
{
    public static function isEnabled(): bool
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            return false;
        }

        $otp = config('services.evolution.login_dev_otp');

        return is_string($otp) && preg_match('/^\d{6}$/', $otp) === 1;
    }

    public static function code(): ?string
    {
        if (! self::isEnabled()) {
            return null;
        }

        $otp = config('services.evolution.login_dev_otp');

        return is_string($otp) ? $otp : null;
    }

    public static function isDevPhone(?string $normalizedPhone): bool
    {
        if (! self::isEnabled() || $normalizedPhone === null) {
            return false;
        }

        $phones = config('services.evolution.login_dev_phones', '');

        if (! is_string($phones) || $phones === '') {
            return false;
        }

        foreach (explode(',', $phones) as $phone) {
            if (! is_string($phone)) {
                continue;
            }

            if (PhoneNumber::normalize($phone) === $normalizedPhone) {
                return true;
            }
        }

        return false;
    }
}
