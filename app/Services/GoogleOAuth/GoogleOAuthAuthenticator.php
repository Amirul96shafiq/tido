<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Enums\GoogleOAuthLoginEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final class GoogleOAuthAuthenticator
{
    public function __construct(
        private readonly GoogleOAuthLoginLogService $loginLogService,
    ) {}

    public function resolveUser(SocialiteUser $googleUser): ?User
    {
        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (! filled($googleId) || ! filled($email)) {
            return null;
        }

        $emailVerified = data_get($googleUser->user, 'email_verified', true);

        if ($emailVerified === false || $emailVerified === 'false' || $emailVerified === 0) {
            return null;
        }

        $byGoogleId = User::query()
            ->where('google_id', $googleId)
            ->first();

        if ($byGoogleId instanceof User) {
            return $this->authorizePrimary($byGoogleId) ? $byGoogleId : null;
        }

        $byEmail = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->whereNull('google_id')
            ->first();

        if (! $byEmail instanceof User) {
            return null;
        }

        if (! $this->authorizePrimary($byEmail)) {
            return null;
        }

        $byEmail->forceFill([
            'google_id' => $googleId,
            'google_linked_at' => now(),
        ])->save();

        return $byEmail->fresh();
    }

    public function logFailure(?string $message = null): void
    {
        $this->loginLogService->log(
            GoogleOAuthLoginEvent::Failed,
            'failed',
            null,
            $message ?? 'Google sign-in is not available for this account.',
        );
    }

    public function logSuccess(User $user): void
    {
        $this->loginLogService->log(
            GoogleOAuthLoginEvent::SignIn,
            'success',
            $user,
            'Signed in via Google.',
        );
    }

    private function authorizePrimary(User $user): bool
    {
        if (! $user->isPrimary()) {
            return false;
        }

        if (! $user instanceof FilamentUser) {
            return false;
        }

        return $user->canAccessPanel(Filament::getPanel('admin'));
    }
}
