<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SendWhatsAppManualInvoiceReceivedAckJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class WhatsAppManualInvoiceReceivedDebouncer
{
    public static function cacheKey(string $senderNumber): string
    {
        return 'wa:manual-invoice-received:'.$senderNumber;
    }

    public static function lockKey(string $senderNumber): string
    {
        return self::cacheKey($senderNumber).':lock';
    }

    /**
     * Record a saved manual WhatsApp invoice and schedule a batched received ack.
     * Label parsing is dispatched only after that ack is sent.
     */
    public static function register(string $senderNumber, int $invoiceId): void
    {
        $senderNumber = trim($senderNumber);

        if ($senderNumber === '' || $invoiceId < 1) {
            return;
        }

        $key = self::cacheKey($senderNumber);
        $token = (string) Str::uuid();
        $ttl = now()->addMinutes(5);
        $seconds = max(1, (int) config('services.evolution.document_received_debounce_seconds', 3));

        Cache::lock(self::lockKey($senderNumber), 5)->block(5, function () use ($key, $token, $ttl, $invoiceId): void {
            $current = Cache::get($key, ['count' => 0, 'token' => null, 'invoice_ids' => []]);
            $invoiceIds = array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                array_merge($current['invoice_ids'] ?? [], [$invoiceId]),
            )));

            Cache::put($key, [
                'count' => count($invoiceIds),
                'token' => $token,
                'invoice_ids' => $invoiceIds,
            ], $ttl);
        });

        SendWhatsAppManualInvoiceReceivedAckJob::dispatch($senderNumber, $token)
            ->delay(now()->addSeconds($seconds));
    }
}
