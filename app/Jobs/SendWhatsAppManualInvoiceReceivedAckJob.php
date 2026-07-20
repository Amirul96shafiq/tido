<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppManualInvoiceReceivedDebouncer;
use App\Support\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendWhatsAppManualInvoiceReceivedAckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $senderNumber,
        public string $token,
    ) {
        $this->onQueue('whatsapp');
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
        $key = WhatsAppManualInvoiceReceivedDebouncer::cacheKey($this->senderNumber);
        $count = 0;
        /** @var list<int> $invoiceIds */
        $invoiceIds = [];

        Cache::lock(WhatsAppManualInvoiceReceivedDebouncer::lockKey($this->senderNumber), 5)
            ->block(5, function () use ($key, &$count, &$invoiceIds): void {
                $payload = Cache::get($key);

                if (! is_array($payload) || ($payload['token'] ?? null) !== $this->token) {
                    return;
                }

                $invoiceIds = array_values(array_map(
                    static fn (mixed $id): int => (int) $id,
                    $payload['invoice_ids'] ?? [],
                ));
                $count = max((int) ($payload['count'] ?? 0), count($invoiceIds));
                Cache::forget($key);
            });

        if ($count < 1) {
            return;
        }

        $waService->sendMessage(
            $this->senderNumber,
            WhatsAppMessage::manualInvoiceReceived($count),
        );

        foreach ($invoiceIds as $invoiceId) {
            if ($invoiceId > 0) {
                ParseManualWhatsAppInvoiceJob::dispatch($invoiceId);
            }
        }
    }
}
