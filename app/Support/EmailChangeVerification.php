<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Email-change verification signed URLs + helpers.
 * Lifetime is owned by AuthLinkExpiry (auth.verification.expire seconds).
 */
final class EmailChangeVerification
{
    public static function expireSeconds(): int
    {
        return AuthLinkExpiry::seconds();
    }

    public static function expiresAt(): Carbon
    {
        return AuthLinkExpiry::expiresAt();
    }

    public static function expireLabel(): string
    {
        return AuthLinkExpiry::label();
    }

    public static function verifyUrl(MustVerifyEmail|Model|Authenticatable $user, string $newEmail): string
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return URL::temporarySignedRoute(
            $panel->generateRouteName('auth.email-change-verification.verify'),
            self::expiresAt(),
            [
                'id' => $user->getKey(),
                'email' => encrypt($newEmail),
            ],
        );
    }

    public static function blockUrl(
        MustVerifyEmail|Model|Authenticatable $user,
        string $newEmail,
        string $verificationSignature,
    ): string {
        $panel = Filament::getCurrentOrDefaultPanel();

        return URL::temporarySignedRoute(
            $panel->generateRouteName('auth.email-change-verification.block-verification'),
            self::expiresAt(),
            [
                'id' => $user->getKey(),
                'email' => encrypt($newEmail),
                'verificationSignature' => $verificationSignature,
            ],
        );
    }

    public static function passwordResetUrl(string $token, CanResetPassword|Model|Authenticatable $user): string
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return URL::temporarySignedRoute(
            $panel->generateRouteName('auth.password-reset.reset'),
            AuthLinkExpiry::expiresAt(),
            [
                'email' => $user->getEmailForPasswordReset(),
                'token' => $token,
            ],
        );
    }
}
