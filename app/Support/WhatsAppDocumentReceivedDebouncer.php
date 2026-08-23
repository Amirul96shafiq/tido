<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SendWhatsAppDocumentReceivedAckJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class WhatsAppDocumentReceivedDebouncer
{
    public static function cacheKey(string $senderNumber): string
    {
        return 'wa:doc-received:'.$senderNumber;
    }

    public static function lockKey(string $senderNumber): string
    {
        return self::cacheKey($senderNumber).':lock';
    }

    public static function sentCacheKey(string $senderNumber, string $token): string
    {
        return self::cacheKey($senderNumber).':sent:'.$token;
    }

    public static function wasSent(string $senderNumber, string $token): bool
    {
        return Cache::has(self::sentCacheKey($senderNumber, $token));
    }

    public static function markSent(string $senderNumber, string $token): bool
    {
        return Cache::add(
            self::sentCacheKey($senderNumber, $token),
            true,
            now()->addMinutes(5),
        );
    }

    /**
     * Remove one acknowledged batch while preserving documents registered afterwards.
     *
     * @param  list<string>  $messageIds
     */
    public static function consume(string $senderNumber, string $token, array $messageIds): void
    {
        $key = self::cacheKey($senderNumber);
        $messageIds = array_values(array_unique($messageIds));

        Cache::lock(self::lockKey($senderNumber), 5)->block(5, function () use ($key, $token, $messageIds): void {
            $current = Cache::get($key);

            if (! is_array($current) || ($current['token'] ?? null) === $token) {
                Cache::forget($key);

                return;
            }

            $remainingDocuments = array_values(array_filter(
                $current['documents'] ?? [],
                static fn (mixed $document): bool => is_array($document)
                    && ! in_array((string) ($document['message_id'] ?? ''), $messageIds, true),
            ));

            if ($remainingDocuments === []) {
                Cache::forget($key);

                return;
            }

            $expenseIds = [];

            foreach ($remainingDocuments as $document) {
                $expenseId = $document['expense_id'] ?? null;

                if (
                    ($document['status'] ?? null) === 'accepted'
                    && is_numeric($expenseId)
                    && (int) $expenseId > 0
                ) {
                    $expenseIds[] = (int) $expenseId;
                }
            }

            Cache::put($key, [
                'count' => count($remainingDocuments),
                'token' => (string) ($current['token'] ?? ''),
                'expense_ids' => $expenseIds,
                'documents' => $remainingDocuments,
            ], now()->addMinutes(5));
        });
    }

    /**
     * @param  array{
     *     message_id: string,
     *     expense_id: int|null,
     *     filename: string,
     *     mime_type: string,
     *     page_count: int|null,
     *     status: 'accepted'|'rejected'|'failed',
     *     reason: string|null
     * }  $document
     */
    public static function register(string $senderNumber, array $document): void
    {
        $senderNumber = trim($senderNumber);

        if ($senderNumber === '' || trim($document['message_id']) === '') {
            return;
        }

        $key = self::cacheKey($senderNumber);
        $token = (string) Str::uuid();
        $ttl = now()->addMinutes(5);
        $seconds = max(1, (int) config('services.evolution.document_received_debounce_seconds', 3));
        $documentCount = 0;

        Cache::lock(self::lockKey($senderNumber), 5)->block(5, function () use ($key, $token, $ttl, $document, &$documentCount): void {
            $current = Cache::get($key, ['count' => 0, 'token' => null, 'documents' => []]);
            $currentDocuments = is_array($current) && is_array($current['documents'] ?? null)
                ? $current['documents']
                : [];
            $documents = [];

            foreach ($currentDocuments as $item) {
                if (is_array($item) && ($item['message_id'] ?? null) !== $document['message_id']) {
                    $documents[] = $item;
                }
            }

            $documents[] = $document;
            $documentCount = count($documents);
            $expenseIds = [];

            foreach ($documents as $item) {
                $expenseId = $item['expense_id'] ?? null;

                if (($item['status'] ?? null) === 'accepted' && is_numeric($expenseId) && (int) $expenseId > 0) {
                    $expenseIds[] = (int) $expenseId;
                }
            }

            Cache::put($key, [
                'count' => $documentCount,
                'token' => $token,
                'expense_ids' => $expenseIds,
                'documents' => $documents,
            ], $ttl);
        });

        SendWhatsAppDocumentReceivedAckJob::dispatch($senderNumber, $token)
            ->delay(now()->addSeconds($seconds));
    }
}
