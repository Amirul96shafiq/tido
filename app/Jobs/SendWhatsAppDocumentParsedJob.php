<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppPublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendWhatsAppDocumentParsedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('whatsapp');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 2, 5];
    }

    public function handle(WhatsAppNotificationService $waService): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || $invoice->source !== 'whatsapp' || blank($invoice->whatsapp_sender)) {
            return;
        }

        $sender = (string) $invoice->whatsapp_sender;
        $pendingAck = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));

        if (is_array($pendingAck)) {
            $this->release(1);

            return;
        }

        $editUrl = WhatsAppPublicUrl::withRoot(
            fn (): string => InvoiceResource::getUrl('edit', ['record' => $invoice]),
        );

        $paymentMethod = $invoice->paymentMethod?->name;

        $details = [
            'merchant_name' => $invoice->merchant_name,
            'total_amount' => $invoice->total_amount,
            'payment_method' => $paymentMethod,
        ];

        $message = $invoice->status === 'requires_manual_review'
            ? WhatsAppMessage::documentNeedsReview($editUrl, $details)
            : WhatsAppMessage::documentParsed($editUrl, $details);

        $waService->sendMessage($sender, $message);

        if ($invoice->status === 'parsed') {
            SendDeferredWhatsAppBudgetAlertJob::dispatch($sender, $invoice->id)
                ->delay(now()->addSeconds(2));
        }
    }
}
