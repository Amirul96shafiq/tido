<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Support\OutboundHttpCaBundle;
use Illuminate\Support\Facades\Http;

final class GoogleOAuthCredentialTester
{
    /**
     * @return array{ok: bool, message: string, latencyMs: int}
     */
    public function test(?string $clientId = null, ?string $clientSecret = null): array
    {
        $settings = app(GoogleOAuthSettings::class);
        $clientId = filled($clientId) ? $clientId : $settings->clientId();
        $clientSecret = filled($clientSecret) ? $clientSecret : $settings->clientSecret();

        if (! filled($clientId) || ! filled($clientSecret)) {
            return [
                'ok' => false,
                'message' => 'Client ID and Client Secret are required.',
                'latencyMs' => 0,
            ];
        }

        $start = microtime(true);

        try {
            $response = OutboundHttpCaBundle::applyVerify(Http::asForm())
                ->timeout(10)
                ->connectTimeout(5)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => 'tido-credential-test-invalid-code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $settings->redirectUrl(),
                    'grant_type' => 'authorization_code',
                ]);

            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $body = $response->json();
            $error = is_array($body) ? ($body['error'] ?? null) : null;

            if ($error === 'invalid_client') {
                return [
                    'ok' => false,
                    'message' => 'Google rejected the client credentials.',
                    'latencyMs' => $latencyMs,
                ];
            }

            if ($error === 'invalid_grant' || $error === 'redirect_uri_mismatch') {
                return [
                    'ok' => true,
                    'message' => 'Client credentials accepted by Google.',
                    'latencyMs' => $latencyMs,
                ];
            }

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => 'Client credentials accepted by Google.',
                    'latencyMs' => $latencyMs,
                ];
            }

            return [
                'ok' => false,
                'message' => 'Unexpected response from Google token endpoint.',
                'latencyMs' => $latencyMs,
            ];
        } catch (\Throwable $exception) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $message = 'Cannot reach Google token endpoint.';

            if (str_contains(strtolower($exception->getMessage()), 'ssl certificate')) {
                $message = OutboundHttpCaBundle::sslVerificationMessage();
            }

            return [
                'ok' => false,
                'message' => $message,
                'latencyMs' => $latencyMs,
            ];
        }
    }
}
