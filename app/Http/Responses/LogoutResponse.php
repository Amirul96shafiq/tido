<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\FilamentAuthLogout;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

final class LogoutResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        FilamentAuthLogout::sendLoggedOutNotification();

        return redirect()->to(
            Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl(),
        );
    }
}
