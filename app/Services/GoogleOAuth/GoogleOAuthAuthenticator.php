<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Enums\GoogleOAuthLoginEvent;
use App\Models\User;
use App\Services\EmailSignupOtpService;
use App\Support\EmailSignupDevOtp;
use App\Support\FieldCharacterLimits;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final class GoogleOAuthAuthenticator
{
    public function __construct(
        private readonly GoogleOAuthLoginLogService $loginLogService,
        private readonly EmailSignupOtpService $emailSignupOtpService,
    ) {}

    /**
     * Resolve a Primary for login: google_id first, then verified-email auto-link.
     */
    public function resolveUser(SocialiteUser $googleUser): ?User
    {
        if (! $this->isGoogleEmailVerified($googleUser)) {
            return null;
        }

        $googleId = $googleUser->getId();

        if (! filled($googleId)) {
            return null;
        }

        $user = User::query()
            ->where('google_id', $googleId)
            ->first();

        if ($user instanceof User) {
            return $this->authorizePrimary($user) ? $user : null;
        }

        $email = EmailSignupDevOtp::normalizeEmail($googleUser->getEmail());

        if ($email === null) {
            return null;
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            return null;
        }

        if (! $this->authorizePrimary($user)) {
            return null;
        }

        if (! $this->linkToUser($user, $googleUser)) {
            return null;
        }

        return $user->fresh();
    }

    /**
     * @return array{google_id: string, email: string, name: string}|null
     */
    public function buildSignupPending(SocialiteUser $googleUser): ?array
    {
        if (! $this->isGoogleEmailVerified($googleUser)) {
            return null;
        }

        $googleId = $googleUser->getId();

        if (! filled($googleId)) {
            return null;
        }

        if (User::query()->where('google_id', $googleId)->exists()) {
            return null;
        }

        $email = EmailSignupDevOtp::normalizeEmail($googleUser->getEmail());

        if ($email === null) {
            return null;
        }

        if (User::query()->where('email', $email)->exists()) {
            return null;
        }

        $displayName = $googleUser->getName();
        $name = filled($displayName)
            ? FieldCharacterLimits::truncate((string) $displayName, FieldCharacterLimits::USER_NAME)
            : $this->emailSignupOtpService->deriveNameFromEmail($email);

        return [
            'google_id' => (string) $googleId,
            'email' => $email,
            'name' => $name,
        ];
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

        if (! $this->isGoogleEmailVerified($googleUser)) {
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

    private function isGoogleEmailVerified(SocialiteUser $googleUser): bool
    {
        $emailVerified = data_get($googleUser->user, 'email_verified', true);

        return ! ($emailVerified === false || $emailVerified === 'false' || $emailVerified === 0);
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
