<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Single source of truth for auth link lifetimes (email-change + password-reset).
 *
 * Config: auth.verification.expire (seconds), from AUTH_VERIFICATION_EXPIRE.
 */
final class AuthLinkExpiry
{
    public static function seconds(): int
    {
        return max(1, (int) config('auth.verification.expire', 30));
    }

    public static function expiresAt(): Carbon
    {
        return now()->addSeconds(self::seconds());
    }

    /**
     * Laravel password broker config is minutes; it multiplies by 60 for token TTL.
     */
    public static function passwordExpireMinutes(): float
    {
        return self::seconds() / 60;
    }

    public static function label(): string
    {
        $seconds = self::seconds();

        if ($seconds >= 86400) {
            $days = intdiv($seconds, 86400);

            return $days.' day'.($days === 1 ? '' : 's');
        }

        if ($seconds >= 3600) {
            $hours = intdiv($seconds, 3600);

            return $hours.' hour'.($hours === 1 ? '' : 's');
        }

        if ($seconds >= 60) {
            $minutes = intdiv($seconds, 60);

            return $minutes.' minute'.($minutes === 1 ? '' : 's');
        }

        return $seconds.' second'.($seconds === 1 ? '' : 's');
    }
}
