<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Enums\GoogleOAuthLoginEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final class GoogleOAuthAuthenticator
{
    public function __construct(
        private readonly GoogleOAuthLoginLogService $loginLogService,
    ) {}

    /**
     * Login: only Primaries already linked by google_id.
     */
    public function resolveUser(SocialiteUser $googleUser): ?User
    {
        $googleId = $googleUser->getId();

        if (! filled($googleId)) {
            return null;
        }

        $emailVerified = data_get($googleUser->user, 'email_verified', true);

        if ($emailVerified === false || $emailVerified === 'false' || $emailVerified === 0) {
            return null;
        }

        $user = User::query()
            ->where('google_id', $googleId)
            ->first();

        if (! $user instanceof User) {
            return null;
        }

        return $this->authorizePrimary($user) ? $user : null;
    }

    /**
     * Link while authenticated: attach google_id to the acting Primary.
     */
    public function linkToUser(User $user, SocialiteUser $googleUser): bool
    {
        if (! $this->authorizePrimary($user)) {
            return false;
        }

        $googleId = $googleUser->getId();

        if (! filled($googleId)) {
            return false;
        }

        $emailVerified = data_get($googleUser->user, 'email_verified', true);

        if ($emailVerified === false || $emailVerified === 'false' || $emailVerified === 0) {
            return false;
        }

        $taken = User::query()
            ->where('google_id', $googleId)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($taken) {
            return false;
        }

        $user->forceFill([
            'google_id' => $googleId,
            'google_linked_at' => now(),
        ])->save();

        return true;
    }

    public function logFailure(?string $message = null, ?int $householdId = null): void
    {
        $this->loginLogService->log(
            GoogleOAuthLoginEvent::Failed,
            'failed',
            null,
            $message ?? 'Google sign-in is not available for this account.',
            $householdId,
        );
    }

    public function logSuccess(User $user): void
    {
        $this->loginLogService->log(
            GoogleOAuthLoginEvent::SignIn,
            'success',
            $user,
            'Signed in via Google.',
            $user->household_id,
        );
    }

    public function logLinked(User $user): void
    {
        $this->loginLogService->log(
            GoogleOAuthLoginEvent::SignIn,
            'success',
            $user,
            'Google account linked.',
            $user->household_id,
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
