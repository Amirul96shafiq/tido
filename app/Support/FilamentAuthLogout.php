<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class FilamentAuthLogout
{
    public static function logoutToLogin(?Component $livewire = null): void
    {
        Filament::auth()->logout();

        Auth::guard(Filament::getAuthGuard())->logout();

        session()->invalidate();
        session()->regenerateToken();

        if ($livewire !== null) {
            $livewire->redirect(Filament::getLoginUrl(), navigate: true);

            return;
        }

        redirect()->to(Filament::getLoginUrl());
    }
}
