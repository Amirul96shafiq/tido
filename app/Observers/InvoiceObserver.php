<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Invoice;
use App\Jobs\ExtractReceiptDataJob;

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
                ($invoice->invoice_number ?? '') . $dateTimeStr . $invoice->total_amount
            );
        }
    }

    public function created(Invoice $invoice): void
    {
        if ($invoice->status === 'pending') {
            ExtractReceiptDataJob::dispatch($invoice->id);
        }
    }

    public function updated(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['parsed', 'reviewed']) && $invoice->wasChanged('status')) {
            app(\App\Services\BudgetAlertService::class)->checkAlertsForInvoice($invoice);
        }
    }
}
