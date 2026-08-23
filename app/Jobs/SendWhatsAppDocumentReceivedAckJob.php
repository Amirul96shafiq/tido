<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use App\Support\ReceiptPipelineLogger;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppTypingCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendWhatsAppDocumentReceivedAckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $senderNumber,
        public string $token,
    ) {
        $this->onQueue('default');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(WhatsAppNotificationService $waService): void
    {
        $this->sendPendingAcknowledgement($waService);
    }

    public function failed(Throwable $exception): void
    {
        ReceiptPipelineLogger::event('receipt.received_ack', [
            'queue' => $this->queue ?? 'default',
            'outcome' => 'failed',
            'reason' => 'maximum_retries',
        ]);

        if (! $this->isDatabaseLocked($exception)) {
            return;
        }

        try {
            $this->sendPendingAcknowledgement(app(WhatsAppNotificationService::class));
        } catch (Throwable $fallbackException) {
            Log::error('Unable to deliver WhatsApp document acknowledgement after queue failure', [
                'error' => $fallbackException->getMessage(),
            ]);
        }
    }

    protected function sendPendingAcknowledgement(WhatsAppNotificationService $waService): void
    {
        $startedAt = ReceiptPipelineLogger::start();
        $key = WhatsAppDocumentReceivedDebouncer::cacheKey($this->senderNumber);
        $count = 0;
        $alreadySent = false;
        /** @var list<int> $expenseIds */
        $expenseIds = [];
        /** @var list<array<string, mixed>> $documents */
        $documents = [];
        /** @var list<string> $messageIds */
        $messageIds = [];

        Cache::lock(WhatsAppDocumentReceivedDebouncer::lockKey($this->senderNumber), 5)
            ->block(5, function () use ($key, &$count, &$alreadySent, &$expenseIds, &$documents, &$messageIds): void {
                $payload = Cache::get($key);

                if (! is_array($payload) || ($payload['token'] ?? null) !== $this->token) {
                    return;
                }

                $expenseIds = array_values(array_map(
                    static fn (mixed $id): int => (int) $id,
                    $payload['expense_ids'] ?? [],
                ));
                $count = max((int) ($payload['count'] ?? 0), count($expenseIds));
                $documents = array_values(array_filter(
                    $payload['documents'] ?? [],
                    static fn (mixed $document): bool => is_array($document),
                ));
                $count = max($count, count($documents));
                $alreadySent = WhatsAppDocumentReceivedDebouncer::wasSent(
                    $this->senderNumber,
                    $this->token,
                );
                $messageIds = array_values(array_filter(
                    array_map(
                        static fn (mixed $document): string => (string) ($document['message_id'] ?? ''),
                        $documents,
                    ),
                    static fn (string $messageId): bool => $messageId !== '',
                ));
            });

        if ($count < 1) {
            ReceiptPipelineLogger::completed('receipt.received_ack', $startedAt, [
                'queue' => $this->queue ?? 'default',
                'outcome' => 'ignored',
            ]);

            return;
        }

        $correlationExpenseId = $expenseIds[0] ?? null;
        $messageId = $messageIds[0] ?? null;

        if (! $alreadySent) {
            $result = $waService->sendMessageResult(
                $this->senderNumber,
                WhatsAppMessage::documentReceived($count, $documents),
                $messageId,
                $correlationExpenseId,
            );

            if (! $result->ok) {
                ReceiptPipelineLogger::completed('receipt.received_ack', $startedAt, [
                    'message_id' => $messageId,
                    'expense_id' => $correlationExpenseId,
                    'queue' => $this->queue ?? 'default',
                    'outcome' => 'failed',
                    'reason' => $result->reason,
                    'status' => $result->status,
                ]);

                throw new RuntimeException(
                    'Unable to send the WhatsApp document received acknowledgement: '.$result->reason,
                );
            }

            WhatsAppDocumentReceivedDebouncer::markSent(
                $this->senderNumber,
                $this->token,
            );
        }

        foreach ($expenseIds as $queuedExpenseId) {
            if ($queuedExpenseId > 0) {
                WhatsAppTypingCoordinator::startExpenseTyping($queuedExpenseId, $this->senderNumber);

                ExtractReceiptDataJob::dispatch($queuedExpenseId);
            }
        }

        WhatsAppDocumentReceivedDebouncer::consume(
            $this->senderNumber,
            $this->token,
            $messageIds,
        );

        ReceiptPipelineLogger::completed('receipt.received_ack', $startedAt, [
            'message_id' => $messageId,
            'expense_id' => $correlationExpenseId,
            'queue' => $this->queue ?? 'default',
            'outcome' => $alreadySent ? 'already_sent' : 'success',
            'document_count' => $count,
        ]);
    }

    protected function isDatabaseLocked(Throwable $exception): bool
    {
        do {
            if (str_contains(strtolower($exception->getMessage()), 'database is locked')) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return false;
    }
}
