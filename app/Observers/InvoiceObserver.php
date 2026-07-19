<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use App\Services\BudgetAlertService;
use App\Services\ReceiptManualReviewNotifier;

class InvoiceObserver
{
    public function creating(Invoice $invoice): void
    {
        if (empty($invoice->receipt_hash)) {
            $dateTimeStr = $invoice->date_time
                ? $invoice->date_time->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');

            $invoice->receipt_hash = hash(
                'sha256',
                ($invoice->invoice_number ?? '').$dateTimeStr.$invoice->total_amount
            );
        }
    }

    public function created(Invoice $invoice): void
    {
        // WhatsApp receipts wait for the batched "Document received" ack before OCR starts.
        if ($invoice->status === 'pending' && $invoice->source !== 'whatsapp') {
            ExtractReceiptDataJob::dispatch($invoice->id);
        }
    }

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status')) {
            return;
        }

        if (in_array($invoice->status, ['parsed', 'reviewed'], true)) {
            app(BudgetAlertService::class)->checkAlertsForInvoice($invoice);
        }

        if ($invoice->status === 'requires_manual_review') {
            app(ReceiptManualReviewNotifier::class)->notify($invoice);
        }
    }
}
