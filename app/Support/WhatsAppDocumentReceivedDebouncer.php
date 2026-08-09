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

        Cache::lock(self::lockKey($senderNumber), 5)->block(5, function () use ($key, $token, $ttl, $document): void {
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
            $expenseIds = [];

            foreach ($documents as $item) {
                $expenseId = $item['expense_id'] ?? null;

                if (($item['status'] ?? null) === 'accepted' && is_numeric($expenseId) && (int) $expenseId > 0) {
                    $expenseIds[] = (int) $expenseId;
                }
            }

            Cache::put($key, [
                'count' => count($documents),
                'token' => $token,
                'expense_ids' => $expenseIds,
                'documents' => $documents,
            ], $ttl);
        });

        SendWhatsAppDocumentReceivedAckJob::dispatch($senderNumber, $token)
            ->delay(now()->addSeconds($seconds));
    }
}
