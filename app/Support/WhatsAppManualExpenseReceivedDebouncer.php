<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SendWhatsAppManualExpenseReceivedAckJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class WhatsAppManualExpenseReceivedDebouncer
{
    public static function cacheKey(string $senderNumber): string
    {
        return 'wa:manual-expense-received:'.$senderNumber;
    }

    public static function lockKey(string $senderNumber): string
    {
        return self::cacheKey($senderNumber).':lock';
    }

    /**
     * Record a saved manual WhatsApp expense and schedule a batched received ack.
     * Label parsing is dispatched only after that ack is sent.
     */
    public static function register(string $senderNumber, int $expenseId): void
    {
        $senderNumber = trim($senderNumber);

        if ($senderNumber === '' || $expenseId < 1) {
            return;
        }

        $key = self::cacheKey($senderNumber);
        $token = (string) Str::uuid();
        $ttl = now()->addMinutes(5);
        $seconds = max(1, (int) config('services.evolution.document_received_debounce_seconds', 3));

        Cache::lock(self::lockKey($senderNumber), 5)->block(5, function () use ($key, $token, $ttl, $expenseId): void {
            $current = Cache::get($key, ['count' => 0, 'token' => null, 'expense_ids' => []]);
            $expenseIds = array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                array_merge($current['expense_ids'] ?? [], [$expenseId]),
            )));

            Cache::put($key, [
                'count' => count($expenseIds),
                'token' => $token,
                'expense_ids' => $expenseIds,
            ], $ttl);
        });

        SendWhatsAppManualExpenseReceivedAckJob::dispatch($senderNumber, $token)
            ->delay(now()->addSeconds($seconds));
    }
}
