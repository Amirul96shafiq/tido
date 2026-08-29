<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Notifications\Notification;

final class FilamentAuthLogin
{
    public static function sendSignedInViaOtp(): void
    {
        self::sendSignedInNotification('Signed in successfully, via OTP');
    }

    public static function sendSignedInViaEmail(): void
    {
        self::sendSignedInNotification('Signed in successfully, via Email Sign In');
    }

    public static function sendSignedInViaGoogle(): void
    {
        self::sendSignedInNotification('Signed in successfully, via Google Account');
    }

    private static function sendSignedInNotification(string $title): void
    {
        Notification::make()
            ->title($title)
            ->success()
            ->send();
    }
}
