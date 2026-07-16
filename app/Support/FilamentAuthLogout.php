<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class FilamentAuthLogout
{
    public static function sendLoggedOutNotification(): void
    {
        Notification::make()
            ->title('Signed out successfully')
            ->success()
            ->send();
    }

    public static function logoutToLogin(?Component $livewire = null): void
    {
        Filament::auth()->logout();

        Auth::guard(Filament::getAuthGuard())->logout();

        session()->invalidate();
        session()->regenerateToken();

        self::sendLoggedOutNotification();

        if ($livewire !== null) {
            $livewire->redirect(Filament::getLoginUrl(), navigate: true);

            return;
        }

        redirect()->to(Filament::getLoginUrl());
    }
}
