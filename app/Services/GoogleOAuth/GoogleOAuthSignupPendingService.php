<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use Illuminate\Support\Facades\Cache;

final class GoogleOAuthSignupPendingService
{
    public const SESSION_KEY = 'google_signup_pending';

    private const TTL_SECONDS = 900;

    /**
     * @param  array{google_id: string, email: string, name: string}  $payload
     */
    public function storeHandoff(array $payload): string
    {
        $token = bin2hex(random_bytes(32));

        Cache::put(self::handoffCacheKey($token), [
            'type' => 'signup_pending',
            'google_id' => $payload['google_id'],
            'email' => $payload['email'],
            'name' => $payload['name'],
        ], now()->addSeconds(self::TTL_SECONDS));

        return $token;
    }

    /**
     * @return array{google_id: string, email: string, name: string}|null
     */
    public function pullHandoff(string $token): ?array
    {
        if (strlen($token) < 32) {
            return null;
        }

        $payload = Cache::pull(self::handoffCacheKey($token));

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'signup_pending') {
            return null;
        }

        if (! isset($payload['google_id'], $payload['email'], $payload['name'])) {
            return null;
        }

        return [
            'google_id' => (string) $payload['google_id'],
            'email' => (string) $payload['email'],
            'name' => (string) $payload['name'],
        ];
    }

    /**
     * @param  array{google_id: string, email: string, name: string}  $payload
     */
    public function bindToSession(array $payload): void
    {
        session([
            self::SESSION_KEY => $payload,
            'google_oauth_signup_panel' => true,
        ]);
    }

    /**
     * @return array{google_id: string, email: string, name: string}|null
     */
    public function fromSession(): ?array
    {
        $payload = session(self::SESSION_KEY);

        if (! is_array($payload) || ! isset($payload['google_id'], $payload['email'], $payload['name'])) {
            return null;
        }

        return [
            'google_id' => (string) $payload['google_id'],
            'email' => (string) $payload['email'],
            'name' => (string) $payload['name'],
        ];
    }

    public function forgetSession(): void
    {
        session()->forget([self::SESSION_KEY, 'google_oauth_signup_panel']);
    }

    public function forgetEmailOtpPending(string $email): void
    {
        Cache::forget('email_signup_otp:'.$email);
        Cache::forget('email_signup_pending:'.$email);
        Cache::forget('email_signup_otp_cooldown:'.$email);
    }

    public static function handoffCacheKey(string $token): string
    {
        return 'google_oauth_handoff:'.$token;
    }
}
