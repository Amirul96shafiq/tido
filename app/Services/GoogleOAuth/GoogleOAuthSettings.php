<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Models\GoogleOAuthSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-global Google OAuth app credentials (one Client ID for the install).
 */
final class GoogleOAuthSettings
{
    private ?GoogleOAuthSetting $cachedRecord = null;

    public static function platform(): self
    {
        return new self;
    }

    public function record(): GoogleOAuthSetting
    {
        if ($this->cachedRecord instanceof GoogleOAuthSetting) {
            return $this->cachedRecord;
        }

        if (! Schema::hasTable('google_oauth_settings')) {
            return $this->cachedRecord = new GoogleOAuthSetting([
                'household_id' => GoogleOAuthSetting::PLATFORM_HOUSEHOLD_ID,
            ]);
        }

        return $this->cachedRecord = GoogleOAuthSetting::platform();
    }

    public function clientId(): ?string
    {
        $clientId = $this->record()->client_id;

        if (filled($clientId)) {
            return $clientId;
        }

        $env = config('services.google.client_id');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function clientSecret(): ?string
    {
        $secret = $this->record()->client_secret;

        if (filled($secret)) {
            return $secret;
        }

        $env = config('services.google.client_secret');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function redirectUrl(): string
    {
        $configured = config('services.google.redirect');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return url('/admin/auth/google/callback');
    }

    public function authorizeUrl(): string
    {
        $callback = $this->redirectUrl();

        if (str_ends_with($callback, '/callback')) {
            return substr($callback, 0, -strlen('callback')).'redirect';
        }

        return route('filament.admin.auth.google.redirect');
    }

    /**
     * Link starts on the current host (auth cookie), then may hand off to the
     * redirect-URI host so Socialite state survives the Google round-trip.
     */
    public function linkAuthorizeUrl(): string
    {
        return route('filament.admin.auth.google.link');
    }

    /**
     * Absolute link URL on the same host as GOOGLE_REDIRECT_URI / callback.
     */
    public function linkUrlOnRedirectHost(): string
    {
        $callback = $this->redirectUrl();

        if (str_ends_with($callback, '/callback')) {
            return substr($callback, 0, -strlen('callback')).'link';
        }

        return route('filament.admin.auth.google.link');
    }

    public function requestHostMatchesRedirectHost(?string $host = null): bool
    {
        $redirectHost = parse_url($this->redirectUrl(), PHP_URL_HOST);
        $requestHost = $host ?? request()->getHost();

        if (! is_string($redirectHost) || ! is_string($requestHost) || $redirectHost === '' || $requestHost === '') {
            return false;
        }

        return strcasecmp($redirectHost, $requestHost) === 0;
    }

    public function usesCrossHostRedirect(): bool
    {
        $redirectHost = parse_url($this->redirectUrl(), PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($redirectHost) || ! is_string($appHost)) {
            return false;
        }

        return strcasecmp($redirectHost, $appHost) !== 0;
    }

    public function hasCredentials(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    /**
     * Login CTA appears when the platform OAuth client is configured.
     */
    public function isSignInAvailable(): bool
    {
        return $this->hasCredentials();
    }

    public function isSetupComplete(): bool
    {
        return $this->record()->setup_completed_at !== null;
    }

    public function usesSavedSettings(): bool
    {
        $record = $this->record();

        return filled($record->client_id)
            || filled($record->client_secret)
            || $record->setup_completed_at !== null;
    }

    /**
     * @return array{client_id: string, client_secret: string, redirect: string}
     */
    public function socialiteConfig(): array
    {
        return [
            'client_id' => (string) $this->clientId(),
            'client_secret' => (string) $this->clientSecret(),
            'redirect' => $this->redirectUrl(),
        ];
    }

    /**
     * @param  array{
     *     client_id?: string|null,
     *     client_secret?: string|null,
     *     enabled?: bool|null,
     *     setup_completed_at?: Carbon|null,
     * }  $attributes
     */
    public function save(array $attributes): GoogleOAuthSetting
    {
        $record = GoogleOAuthSetting::platform();

        if (! array_key_exists('enabled', $attributes)) {
            $attributes['enabled'] = true;
        }

        $record->fill($attributes);
        $record->save();

        return $this->cachedRecord = $record->refresh();
    }

    public function reset(): void
    {
        $record = GoogleOAuthSetting::platform();
        $record->fill([
            'client_id' => null,
            'client_secret' => null,
            'enabled' => false,
            'setup_completed_at' => null,
        ]);
        $record->save();

        $this->cachedRecord = $record->refresh();
    }

    public function forgetCache(): void
    {
        $this->cachedRecord = null;
    }
}
