<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class Register extends Login
{
    public string $authPanel = 'sign-up';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        if (session()->pull('google_oauth_error')) {
            Notification::make()
                ->title('Google sign-in failed')
                ->body('Google sign-in is not available for this account.')
                ->danger()
                ->send();
        }

        session()->pull('google_oauth_signup_panel');

        $this->form->fill();

        $this->authPanel = 'sign-up';
        $this->signupMode = 'form';
        $this->restoreGoogleSignupPendingFromSession();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sign Up';
    }

    public function selectSignInTab(): void
    {
        $this->redirect(Filament::getLoginUrl(), navigate: true);
    }

    public function selectSignUpTab(): void
    {
        if (Filament::getRegistrationUrl() === url()->current()) {
            return;
        }

        $this->redirect(Filament::getRegistrationUrl(), navigate: true);
    }
}
