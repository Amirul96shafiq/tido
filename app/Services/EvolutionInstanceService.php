<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EvolutionInstanceService
{
    private string $apiUrl;

    private string $apiKey;

    private string $instanceName;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('services.evolution.api_url'), '/');
        $this->apiKey = (string) config('services.evolution.api_key');
        $this->instanceName = (string) config('services.evolution.instance_name', 'tido');
    }

    public function instanceName(): string
    {
        return $this->instanceName;
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && $this->apiKey !== '';
    }

    /**
     * @return array{ok: bool, status: string, message: string, raw: array<string, mixed>|null}
     */
    public function connectionState(): array
    {
        try {
            $response = $this->client()
                ->get("{$this->apiUrl}/instance/connectionState/{$this->instanceName}")
                ->throw()
                ->json();

            $state = data_get($response, 'instance.state')
                ?? data_get($response, 'state')
                ?? data_get($response, 'status')
                ?? 'unknown';

            return [
                'ok' => true,
                'status' => (string) $state,
                'message' => 'Connection state loaded.',
                'raw' => is_array($response) ? $response : null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 'unreachable',
                'message' => $this->friendlyError($e),
                'raw' => null,
            ];
        }
    }

    /**
     * Prefer a fresh connect QR (instance usually already exists). Create if missing.
     *
     * @return array{ok: bool, status: string, qrBase64: string|null, message: string, raw: array<string, mixed>|null}
     */
    public function createOrConnect(): array
    {
        $connect = $this->connectInstance();

        if ($connect['ok'] && $connect['qrBase64'] !== null) {
            return $connect;
        }

        if ($connect['ok'] && $connect['qrBase64'] === null && $this->isConnectedStatus($connect['status'])) {
            return $connect;
        }

        $create = $this->createInstance();

        if ($create['ok']) {
            return $create;
        }

        if ($this->shouldFallbackToConnect($create['message'])) {
            $retry = $this->connectInstance();

            if ($retry['ok']) {
                return $retry;
            }

            return [
                'ok' => false,
                'status' => 'error',
                'qrBase64' => null,
                'message' => $this->combineAuthHints($create['message'], $retry['message']),
                'raw' => null,
            ];
        }

        return [
            'ok' => false,
            'status' => 'error',
            'qrBase64' => null,
            'message' => $this->combineAuthHints($connect['message'], $create['message']),
            'raw' => null,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function logoutInstance(): array
    {
        try {
            $this->client()
                ->delete("{$this->apiUrl}/instance/logout/{$this->instanceName}")
                ->throw();

            return [
                'ok' => true,
                'message' => 'Logged out of WhatsApp session. Generate a new QR to pair again.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
            ];
        }
    }

    private function isConnectedStatus(string $status): bool
    {
        $normalized = strtolower($status);

        return in_array($normalized, ['open', 'connected'], true);
    }

    /**
     * @return array{ok: bool, status: string, qrBase64: string|null, message: string, raw: array<string, mixed>|null}
     */
    public function createInstance(): array
    {
        try {
            $response = $this->client()
                ->post("{$this->apiUrl}/instance/create", [
                    'instanceName' => $this->instanceName,
                    'token' => $this->apiKey,
                    'qrcode' => true,
                    'integration' => 'WHATSAPP-BAILEYS',
                ])
                ->throw()
                ->json();

            return $this->qrResultFromPayload(is_array($response) ? $response : [], 'Instance created. Scan the QR with WhatsApp.');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 'error',
                'qrBase64' => null,
                'message' => $this->friendlyError($e),
                'raw' => null,
            ];
        }
    }

    /**
     * @return array{ok: bool, status: string, qrBase64: string|null, message: string, raw: array<string, mixed>|null}
     */
    public function connectInstance(): array
    {
        try {
            $response = $this->client()
                ->get("{$this->apiUrl}/instance/connect/{$this->instanceName}")
                ->throw()
                ->json();

            return $this->qrResultFromPayload(is_array($response) ? $response : [], 'Scan the QR with WhatsApp Linked Devices.');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 'error',
                'qrBase64' => null,
                'message' => $this->friendlyError($e),
                'raw' => null,
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function registerWebhook(?string $webhookUrl = null): array
    {
        $url = $webhookUrl ?? $this->defaultWebhookUrl();

        try {
            $this->client()
                ->post("{$this->apiUrl}/webhook/set/{$this->instanceName}", [
                    'webhook' => [
                        'enabled' => true,
                        'url' => $url,
                        'headers' => [
                            'Authorization' => 'Bearer '.$this->apiKey,
                        ],
                        'byEvents' => false,
                        'base64' => false,
                        'events' => [
                            'MESSAGES_UPSERT',
                        ],
                    ],
                ])
                ->throw();

            return [
                'ok' => true,
                'message' => "Webhook registered: {$url}",
            ];
        } catch (\Throwable $e) {
            Log::warning('Evolution webhook registration failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
            ];
        }
    }

    public function defaultWebhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/webhooks/whatsapp';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: string, qrBase64: string|null, message: string, raw: array<string, mixed>}
     */
    private function qrResultFromPayload(array $payload, string $successMessage): array
    {
        $qr = data_get($payload, 'qrcode.base64')
            ?? data_get($payload, 'base64')
            ?? data_get($payload, 'qrcode.base64');

        $status = (string) (data_get($payload, 'instance.status')
            ?? data_get($payload, 'instance.state')
            ?? data_get($payload, 'status')
            ?? 'connecting');

        $qrBase64 = is_string($qr) && $qr !== '' ? $qr : null;

        if ($qrBase64 !== null && ! str_starts_with($qrBase64, 'data:')) {
            $qrBase64 = 'data:image/png;base64,'.$qrBase64;
        }

        return [
            'ok' => true,
            'status' => $status,
            'qrBase64' => $qrBase64,
            'message' => $qrBase64 === null
                ? 'Request succeeded but no QR was returned. Refresh QR or check Evolution logs.'
                : $successMessage,
            'raw' => $payload,
        ];
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Evolution API is not configured. Set EVOLUTION_API_URL and EVOLUTION_API_KEY.');
        }

        return Http::timeout(60)
            ->acceptJson()
            ->withHeaders(['apikey' => $this->apiKey]);
    }

    private function shouldFallbackToConnect(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'already')
            || str_contains($lower, 'exist')
            || str_contains($lower, 'in use')
            || str_contains($lower, 'unauthorized')
            || str_contains($lower, 'forbidden')
            || str_contains($lower, 'not found')
            || str_contains($lower, 'does not exist');
    }

    private function combineAuthHints(string $createMessage, string $connectMessage): string
    {
        $hint = 'Check that tido EVOLUTION_API_KEY matches Evolution AUTHENTICATION_API_KEY, then restart both apps.';

        if (str_contains(strtolower($createMessage), 'unauthorized')
            || str_contains(strtolower($connectMessage), 'unauthorized')) {
            return "Unauthorized from Evolution. {$hint}";
        }

        return trim($createMessage.' | '.$connectMessage.' '.$hint);
    }

    private function friendlyError(\Throwable $e): string
    {
        if ($e instanceof RequestException && $e->response !== null) {
            $body = $e->response->json();
            $message = data_get($body, 'response.message')
                ?? data_get($body, 'message')
                ?? data_get($body, 'error')
                ?? $e->response->body();

            if (is_array($message)) {
                $message = implode(' ', array_map(
                    static fn (mixed $part): string => is_string($part) ? $part : (json_encode($part) ?: ''),
                    $message,
                ));
            }

            $status = $e->response->status();

            if ($status === 401 || str_contains(strtolower((string) $message), 'unauthorized')) {
                return 'Unauthorized — EVOLUTION_API_KEY must equal Evolution AUTHENTICATION_API_KEY (restart tido after editing .env).';
            }

            return trim((string) $message) !== ''
                ? (string) $message
                : 'Evolution API request failed (HTTP '.$status.').';
        }

        return $e->getMessage();
    }
}
