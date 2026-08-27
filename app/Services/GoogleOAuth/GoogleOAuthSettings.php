<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Models\GoogleOAuthSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class GoogleOAuthSettings
{
    private ?GoogleOAuthSetting $cachedRecord = null;

    public function record(): GoogleOAuthSetting
    {
        if ($this->cachedRecord instanceof GoogleOAuthSetting) {
            return $this->cachedRecord;
        }

        if (! Schema::hasTable('google_oauth_settings')) {
            return $this->cachedRecord = new GoogleOAuthSetting(['id' => GoogleOAuthSetting::SINGLETON_ID]);
        }

        return $this->cachedRecord = GoogleOAuthSetting::singleton();
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

    public function enabled(): bool
    {
        if ($this->record()->enabled) {
            return true;
        }

        return (bool) config('services.google.enabled', false);
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

    public function isSignInAvailable(): bool
    {
        return $this->enabled() && $this->hasCredentials();
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
            || $record->enabled
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
        $record = GoogleOAuthSetting::singleton();
        $record->fill($attributes);
        $record->save();

        return $this->cachedRecord = $record->refresh();
    }

    public function reset(): void
    {
        $record = GoogleOAuthSetting::singleton();
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
