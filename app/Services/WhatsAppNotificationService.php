<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PhoneNumber;
use App\Support\WhatsAppSendResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    protected string $apiUrl;

    protected string $apiKey;

    protected string $instanceName;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('services.evolution.api_url'), '/');
        $this->apiKey = (string) config('services.evolution.api_key');
        $this->instanceName = (string) config('services.evolution.instance_name');
    }

    public function sendMessage(string $number, string $text): bool
    {
        return $this->sendMessageResult($number, $text)->ok;
    }

    public function sendMessageResult(string $number, string $text): WhatsAppSendResult
    {
        try {
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
                    'body' => $body,
                ]);

                return WhatsAppSendResult::failure(
                    reason: $this->classifySendFailure($body),
                    detail: $this->extractErrorDetail($body),
                    status: $response->status(),
                );
            }

            return WhatsAppSendResult::success();
        } catch (\Throwable $e) {
            Log::error('WhatsAppNotificationService send error', ['error' => $e->getMessage()]);

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
                    'body' => $response->body(),
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
        return Http::timeout(15)
            ->connectTimeout(5)
            ->acceptJson()
            ->withHeaders(['apikey' => $this->apiKey]);
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
}
