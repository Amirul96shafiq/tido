<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PhoneNumber;
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
                ?? data_get($response, 'instance.connectionStatus')
                ?? data_get($response, 'state')
                ?? data_get($response, 'status');

            $status = is_string($state) && $state !== ''
                ? $state
                : 'unknown';

            // Socket endpoint can return a null/empty state while Prisma already has open
            // (e.g. connectionStatus stored as a plain string without `.state`).
            if (! $this->isConnectedStatus($status) && in_array(strtolower($status), ['unknown', ''], true)) {
                $details = $this->fetchInstanceDetails();
                $detailStatus = (string) ($details['connectionStatus'] ?? '');

                if ($details['ok'] && $this->isConnectedStatus($detailStatus) && filled($details['connectedNumber'])) {
                    return [
                        'ok' => true,
                        'status' => 'open',
                        'message' => 'Connection state loaded.',
                        'raw' => is_array($details['raw']) ? $details['raw'] : (is_array($response) ? $response : null),
                    ];
                }
            }

            return [
                'ok' => true,
                'status' => $status,
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
     * @return array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}
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

            return $this->connectErrorResult($this->combineAuthHints($create['message'], $retry['message']));
        }

        return $this->connectErrorResult($this->combineAuthHints($connect['message'], $create['message']));
    }

    /**
     * Request a phone-number pairing code for the given WhatsApp account.
     *
     * @return array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}
     */
    public function createOrConnectWithPairingCode(string $number): array
    {
        $connect = $this->connectInstance($number);

        if ($connect['ok'] && $connect['pairingCode'] !== null) {
            return $connect;
        }

        if ($connect['ok'] && $this->isConnectedStatus($connect['status'])) {
            return $connect;
        }

        // Evolution often returns "connecting" before requestPairingCode finishes.
        // Poll the cached QR payload — do NOT logout (that closes the socket mid-hello).
        if ($connect['ok'] && $this->isConnectingStatus($connect['status']) && $connect['pairingCode'] === null) {
            $polled = $this->pollForPairingCode($number);

            if ($polled !== null) {
                return $polled;
            }
        }

        if (! $connect['ok'] || $this->shouldFallbackToConnect($connect['message'])) {
            $create = $this->createInstance();

            if ($create['ok'] && $create['pairingCode'] !== null) {
                return $create;
            }

            if ($create['ok'] && $this->isConnectedStatus($create['status'])) {
                return $create;
            }

            $retry = $this->connectInstance($number);

            if ($retry['ok'] && $retry['pairingCode'] !== null) {
                return $retry;
            }

            if ($retry['ok'] && $this->isConnectedStatus($retry['status'])) {
                return $retry;
            }

            if ($retry['ok'] && $this->isConnectingStatus($retry['status']) && $retry['pairingCode'] === null) {
                $polled = $this->pollForPairingCode($number);

                if ($polled !== null) {
                    return $polled;
                }
            }

            if (! $create['ok'] && ! $retry['ok']) {
                return $this->connectErrorResult($this->combineAuthHints($create['message'], $retry['message']));
            }

            return $this->connectErrorResult(
                $retry['message'] !== ''
                    ? $retry['message']
                    : 'Evolution did not return a pairing code. Log out the session and try again.',
            );
        }

        return $this->connectErrorResult(
            $connect['message'] !== ''
                ? $connect['message']
                : 'Evolution did not return a pairing code. Log out the session and try again.',
        );
    }

    /**
     * @return array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}|null
     */
    private function pollForPairingCode(string $number, int $attempts = 6, int $delayMs = 1000): ?array
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            usleep($delayMs * 1000);

            $poll = $this->connectInstance($number);

            if ($poll['ok'] && $poll['pairingCode'] !== null) {
                return $poll;
            }

            if ($poll['ok'] && $this->isConnectedStatus($poll['status'])) {
                return $poll;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     ok: bool,
     *     connectedNumber: string|null,
     *     profileName: string|null,
     *     instanceName: string|null,
     *     instanceId: string|null,
     *     integration: string|null,
     *     connectionStatus: string|null,
     *     messageCount: int|null,
     *     contactCount: int|null,
     *     chatCount: int|null,
     *     updatedAt: string|null,
     *     message: string,
     *     raw: array<string, mixed>|null
     * }
     */
    public function fetchInstanceDetails(): array
    {
        $empty = [
            'ok' => false,
            'connectedNumber' => null,
            'profileName' => null,
            'instanceName' => null,
            'instanceId' => null,
            'integration' => null,
            'connectionStatus' => null,
            'messageCount' => null,
            'contactCount' => null,
            'chatCount' => null,
            'updatedAt' => null,
            'message' => 'Instance details unavailable.',
            'raw' => null,
        ];

        try {
            $response = $this->client()
                ->get("{$this->apiUrl}/instance/fetchInstances", [
                    'instanceName' => $this->instanceName,
                ])
                ->throw()
                ->json();

            $instance = $this->firstInstanceFromFetchPayload($response);

            if ($instance === null) {
                return $empty;
            }

            $ownerJid = data_get($instance, 'ownerJid');
            $numberFromOwner = is_string($ownerJid) && $ownerJid !== ''
                ? explode('@', $ownerJid)[0]
                : null;
            $explicitNumber = data_get($instance, 'number');

            $connectedNumber = PhoneNumber::normalize(
                is_string($explicitNumber) && $explicitNumber !== ''
                    ? $explicitNumber
                    : (is_string($numberFromOwner) ? $numberFromOwner : null),
            );

            return [
                'ok' => true,
                'connectedNumber' => $connectedNumber,
                'profileName' => $this->nullableString(data_get($instance, 'profileName')),
                'instanceName' => $this->nullableString(data_get($instance, 'name') ?? $this->instanceName),
                'instanceId' => $this->nullableString(data_get($instance, 'id')),
                'integration' => $this->nullableString(data_get($instance, 'integration')),
                'connectionStatus' => $this->nullableString(data_get($instance, 'connectionStatus')),
                'messageCount' => $this->nullableInt(data_get($instance, '_count.Message')),
                'contactCount' => $this->nullableInt(data_get($instance, '_count.Contact')),
                'chatCount' => $this->nullableInt(data_get($instance, '_count.Chat')),
                'updatedAt' => $this->nullableString(data_get($instance, 'updatedAt')),
                'message' => 'Instance details loaded.',
                'raw' => $instance,
            ];
        } catch (\Throwable $e) {
            return [
                ...$empty,
                'message' => $this->friendlyError($e),
            ];
        }
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
     * @return array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}
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

            return $this->connectResultFromPayload(is_array($response) ? $response : [], 'Instance created. Scan the QR with WhatsApp.');
        } catch (\Throwable $e) {
            return $this->connectErrorResult($this->friendlyError($e));
        }
    }

    /**
     * @return array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}
     */
    public function connectInstance(?string $number = null): array
    {
        try {
            $query = [];

            if ($number !== null && $number !== '') {
                $query['number'] = $number;
            }

            $response = $this->client()
                ->get("{$this->apiUrl}/instance/connect/{$this->instanceName}", $query)
                ->throw()
                ->json();

            $successMessage = $number !== null && $number !== ''
                ? 'Enter the pairing code in WhatsApp Linked Devices.'
                : 'Scan the QR with WhatsApp Linked Devices.';

            return $this->connectResultFromPayload(is_array($response) ? $response : [], $successMessage);
        } catch (\Throwable $e) {
            return $this->connectErrorResult($this->friendlyError($e));
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
     * @return array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}
     */
    private function connectErrorResult(string $message): array
    {
        return [
            'ok' => false,
            'status' => 'error',
            'qrBase64' => null,
            'pairingCode' => null,
            'message' => $message,
            'raw' => null,
        ];
    }

    private function isConnectingStatus(string $status): bool
    {
        return strtolower($status) === 'connecting';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>}
     */
    private function connectResultFromPayload(array $payload, string $successMessage): array
    {
        $qr = data_get($payload, 'qrcode.base64')
            ?? data_get($payload, 'base64')
            ?? data_get($payload, 'qrcode.base64');

        $pairingCode = data_get($payload, 'pairingCode')
            ?? data_get($payload, 'qrcode.pairingCode');

        $status = (string) (data_get($payload, 'instance.state')
            ?? data_get($payload, 'state')
            ?? data_get($payload, 'instance.status')
            ?? data_get($payload, 'status')
            ?? data_get($payload, 'connectionStatus')
            ?? 'connecting');

        $qrBase64 = is_string($qr) && $qr !== '' ? $qr : null;

        if ($qrBase64 !== null && ! str_starts_with($qrBase64, 'data:')) {
            $qrBase64 = 'data:image/png;base64,'.$qrBase64;
        }

        $normalizedPairingCode = is_string($pairingCode) && trim($pairingCode) !== ''
            ? trim($pairingCode)
            : null;

        $message = match (true) {
            $normalizedPairingCode !== null => $successMessage,
            $qrBase64 !== null => $successMessage,
            default => 'Request succeeded but no QR or pairing code was returned. Try again or check Evolution logs.',
        };

        return [
            'ok' => true,
            'status' => $status,
            'qrBase64' => $qrBase64,
            'pairingCode' => $normalizedPairingCode,
            'message' => $message,
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

    /**
     * @return array<string, mixed>|null
     */
    private function firstInstanceFromFetchPayload(mixed $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        if (array_is_list($payload)) {
            $first = $payload[0] ?? null;

            return is_array($first) ? $first : null;
        }

        $nested = data_get($payload, 'instance');

        if (is_array($nested)) {
            return $nested;
        }

        return $payload;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
