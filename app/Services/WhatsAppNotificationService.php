<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\EvolutionCredential;
use App\Support\PhoneNumber;
use App\Support\ReceiptPipelineLogger;
use App\Support\WhatsAppSendResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppNotificationService
{
    protected string $apiUrl;

    protected string $apiKey;

    protected string $instanceName;

    public function __construct(
        private readonly EvolutionInstanceService $evolution,
    ) {
        $this->apiUrl = rtrim((string) config('services.evolution.api_url'), '/');
        $this->apiKey = (string) config('services.evolution.api_key');
        $this->instanceName = (string) config('services.evolution.instance_name');
    }

    public function sendMessage(
        string $number,
        string $text,
        ?string $messageId = null,
        ?int $expenseId = null,
    ): bool {
        return $this->sendMessageResult($number, $text, $messageId, $expenseId)->ok;
    }

    public function sendMessageResult(
        string $number,
        string $text,
        ?string $messageId = null,
        ?int $expenseId = null,
    ): WhatsAppSendResult {
        $startedAt = ReceiptPipelineLogger::start();

        try {
            if (! PhoneNumber::isAllowedWhatsAppSender($number)) {
                $normalized = PhoneNumber::normalize(explode('@', $number, 2)[0]) ?? $number;

                Log::warning('WhatsAppNotificationService blocked non-allowlisted recipient', [
                    'number' => $normalized,
                ]);

                ReceiptPipelineLogger::completed('whatsapp.send_text', $startedAt, [
                    'message_id' => $messageId,
                    'expense_id' => $expenseId,
                    'queue' => 'outbound',
                    'outcome' => 'blocked',
                    'reason' => 'not_allowlisted',
                ]);

                return WhatsAppSendResult::failure(
                    reason: 'not_allowlisted',
                    detail: 'Recipient is not on the contact allowlist.',
                );
            }

            $number = $this->normalizeNumber($number);

            $response = $this->client()
                ->post("{$this->apiUrl}/message/sendText/{$this->instanceName}", [
                    'number' => $number,
                    'text' => $text,
                ]);

            if ($response->failed()) {
                $body = $response->body();

                Log::error('WhatsAppNotificationService send failed', [
                    'status' => $response->status(),
                ]);

                if ($this->bodyIndicatesClosedSocket($body)) {
                    $retry = $this->retrySendAfterSocketRestore(
                        $number,
                        $text,
                        $messageId,
                        $expenseId,
                        $startedAt,
                    );

                    if ($retry !== null) {
                        return $retry;
                    }
                }

                ReceiptPipelineLogger::completed('whatsapp.send_text', $startedAt, [
                    'message_id' => $messageId,
                    'expense_id' => $expenseId,
                    'queue' => 'outbound',
                    'outcome' => 'failed',
                    'reason' => $this->classifySendFailure($body),
                    'status' => $response->status(),
                ]);

                return WhatsAppSendResult::failure(
                    reason: $this->classifySendFailure($body),
                    detail: $this->extractErrorDetail($body),
                    status: $response->status(),
                );
            }

            ReceiptPipelineLogger::completed('whatsapp.send_text', $startedAt, [
                'message_id' => $messageId,
                'expense_id' => $expenseId,
                'queue' => 'outbound',
                'outcome' => 'success',
                'status' => $response->status(),
            ]);

            return WhatsAppSendResult::success();
        } catch (\Throwable $e) {
            Log::error('WhatsAppNotificationService send error', ['error' => $e->getMessage()]);

            ReceiptPipelineLogger::completed('whatsapp.send_text', $startedAt, [
                'message_id' => $messageId,
                'expense_id' => $expenseId,
                'queue' => 'outbound',
                'outcome' => 'error',
                'reason' => 'connection_error',
            ]);

            return WhatsAppSendResult::failure(
                reason: 'connection_error',
                detail: $e->getMessage(),
            );
        }
    }

    public function sendTyping(
        string $number,
        ?int $delayMs = null,
        ?string $messageId = null,
        ?int $expenseId = null,
    ): WhatsAppSendResult {
        $startedAt = ReceiptPipelineLogger::start();

        try {
            if (! PhoneNumber::isAllowedWhatsAppSender($number)) {
                $normalized = PhoneNumber::normalize(explode('@', $number, 2)[0]) ?? $number;

                Log::warning('WhatsAppNotificationService blocked non-allowlisted typing recipient', [
                    'number' => $normalized,
                ]);

                ReceiptPipelineLogger::completed('whatsapp.send_presence', $startedAt, [
                    'message_id' => $messageId,
                    'expense_id' => $expenseId,
                    'queue' => 'outbound',
                    'outcome' => 'blocked',
                    'reason' => 'not_allowlisted',
                ]);

                return WhatsAppSendResult::failure(
                    reason: 'not_allowlisted',
                    detail: 'Recipient is not on the contact allowlist.',
                );
            }

            $number = $this->normalizeNumber($number);
            $delayMs = max(1000, $delayMs ?? max(1000, (int) config('services.evolution.whatsapp_typing_delay_ms', 20000)));

            $response = $this->typingClient($delayMs)
                ->post("{$this->apiUrl}/chat/sendPresence/{$this->instanceName}", [
                    'number' => $number,
                    'presence' => 'composing',
                    'delay' => $delayMs,
                ]);

            if ($response->failed()) {
                $body = $response->body();

                Log::warning('WhatsAppNotificationService typing presence failed', [
                    'status' => $response->status(),
                ]);

                ReceiptPipelineLogger::completed('whatsapp.send_presence', $startedAt, [
                    'message_id' => $messageId,
                    'expense_id' => $expenseId,
                    'queue' => 'outbound',
                    'outcome' => 'failed',
                    'reason' => 'presence_failed',
                    'status' => $response->status(),
                ]);

                return WhatsAppSendResult::failure(
                    reason: 'presence_failed',
                    detail: $this->extractErrorDetail($body),
                    status: $response->status(),
                );
            }

            ReceiptPipelineLogger::completed('whatsapp.send_presence', $startedAt, [
                'message_id' => $messageId,
                'expense_id' => $expenseId,
                'queue' => 'outbound',
                'outcome' => 'success',
                'status' => $response->status(),
            ]);

            return WhatsAppSendResult::success();
        } catch (\Throwable $e) {
            Log::warning('WhatsAppNotificationService typing presence error', ['error' => $e->getMessage()]);

            ReceiptPipelineLogger::completed('whatsapp.send_presence', $startedAt, [
                'message_id' => $messageId,
                'expense_id' => $expenseId,
                'queue' => 'outbound',
                'outcome' => 'error',
                'reason' => 'connection_error',
            ]);

            return WhatsAppSendResult::failure(
                reason: 'connection_error',
                detail: $e->getMessage(),
            );
        }
    }

    /**
     * Check whether a number is registered on WhatsApp via Evolution.
     * Returns null when the check request itself fails.
     */
    public function isWhatsAppNumber(string $number): ?bool
    {
        $digits = PhoneNumber::normalize($number) ?? preg_replace('/\D+/', '', $number) ?? '';

        if ($digits === '') {
            return false;
        }

        try {
            $response = $this->client()
                ->post("{$this->apiUrl}/chat/whatsappNumbers/{$this->instanceName}", [
                    'numbers' => [$digits],
                ]);

            if ($response->failed()) {
                Log::warning('WhatsAppNotificationService number check failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $entries = $this->normalizeNumberCheckEntries($response->json());

            if ($entries === [] || ! array_key_exists('exists', $entries[0])) {
                return null;
            }

            return (bool) $entries[0]['exists'];
        } catch (\Throwable $e) {
            Log::warning('WhatsAppNotificationService number check error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function client(): PendingRequest
    {
        if ($this->apiUrl === '' || ! EvolutionCredential::isValid($this->apiKey)) {
            throw new RuntimeException('Evolution API is not configured. Set EVOLUTION_API_URL and EVOLUTION_API_KEY with a 32+ character value.');
        }

        $timeout = max(1, (int) config('services.evolution.timeout', 15));
        $connectTimeout = max(1, (int) config('services.evolution.connect_timeout', 5));

        return Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->acceptJson()
            ->withHeaders(['apikey' => $this->apiKey]);
    }

    protected function typingClient(int $delayMs): PendingRequest
    {
        if ($this->apiUrl === '' || ! EvolutionCredential::isValid($this->apiKey)) {
            throw new RuntimeException('Evolution API is not configured. Set EVOLUTION_API_URL and EVOLUTION_API_KEY with a 32+ character value.');
        }

        $connectTimeout = max(1, (int) config('services.evolution.connect_timeout', 5));

        return Http::timeout($this->typingHttpTimeoutSeconds($delayMs))
            ->connectTimeout($connectTimeout)
            ->acceptJson()
            ->withHeaders(['apikey' => $this->apiKey]);
    }

    protected function typingHttpTimeoutSeconds(int $delayMs): int
    {
        $delaySeconds = max(1, (int) ceil($delayMs / 1000));
        $connectTimeout = max(1, (int) config('services.evolution.connect_timeout', 5));

        return $delaySeconds + $connectTimeout + 5;
    }

    protected function normalizeNumber(string $number): string
    {
        if (! str_contains($number, '@')) {
            return $number.'@s.whatsapp.net';
        }

        return $number;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeNumberCheckEntries(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            /** @var list<array<string, mixed>> $payload */
            return array_values(array_filter(
                $payload,
                static fn (mixed $entry): bool => is_array($entry),
            ));
        }

        $numbers = $payload['numbers'] ?? null;

        if (is_array($numbers) && array_is_list($numbers)) {
            /** @var list<array<string, mixed>> $numbers */
            return array_values(array_filter(
                $numbers,
                static fn (mixed $entry): bool => is_array($entry),
            ));
        }

        if (array_key_exists('exists', $payload)) {
            /** @var array<string, mixed> $payload */
            return [$payload];
        }

        return [];
    }

    protected function classifySendFailure(string $body): string
    {
        $lower = strtolower($body);

        if (
            str_contains($lower, 'not on whatsapp')
            || str_contains($lower, 'not exist')
            || str_contains($lower, 'does not exist')
            || str_contains($lower, 'exists":false')
            || str_contains($lower, '"exists": false')
            || str_contains($lower, 'invalid number')
            || str_contains($lower, 'number not')
        ) {
            return 'not_on_whatsapp';
        }

        return 'send_failed';
    }

    protected function extractErrorDetail(string $body): ?string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return null;
        }

        $json = json_decode($trimmed, true);

        if (! is_array($json)) {
            return mb_substr($trimmed, 0, 240);
        }

        $message = data_get($json, 'error.message')
            ?? data_get($json, 'message')
            ?? data_get($json, 'error')
            ?? data_get($json, 'response.message');

        if (is_string($message) && trim($message) !== '') {
            return mb_substr(trim($message), 0, 240);
        }

        return mb_substr($trimmed, 0, 240);
    }

    private function bodyIndicatesClosedSocket(string $body): bool
    {
        $lower = strtolower($body);

        return str_contains($lower, 'connection closed')
            || str_contains($lower, 'connectionclosed');
    }

    private function retrySendAfterSocketRestore(
        string $number,
        string $text,
        ?string $messageId,
        ?int $expenseId,
        int $startedAt,
    ): ?WhatsAppSendResult {
        $lockAcquired = Cache::add('evolution:restore-session-socket', 1, 15);
        $restored = false;

        if ($lockAcquired) {
            $restored = $this->evolution->restoreSessionSocket();
        } else {
            $state = $this->evolution->connectionState();
            $restored = in_array(strtolower($state['status']), ['open', 'connected'], true);
        }

        if (! $restored) {
            return null;
        }

        $retry = $this->client()->post("{$this->apiUrl}/message/sendText/{$this->instanceName}", [
            'number' => $number,
            'text' => $text,
        ]);

        if ($retry->failed()) {
            return null;
        }

        Log::info('WhatsAppNotificationService send recovered after socket restore');

        ReceiptPipelineLogger::completed('whatsapp.send_text', $startedAt, [
            'message_id' => $messageId,
            'expense_id' => $expenseId,
            'queue' => 'outbound',
            'outcome' => 'success',
            'reason' => 'socket_restored',
            'status' => $retry->status(),
        ]);

        return WhatsAppSendResult::success();
    }
}
