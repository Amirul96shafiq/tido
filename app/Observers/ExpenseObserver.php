<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Expense;
use App\Services\BudgetAlertService;
use App\Services\ReceiptManualReviewNotifier;

class ExpenseObserver
{
    public function creating(Expense $expense): void
    {
        if (empty($expense->receipt_hash)) {
            $dateTimeStr = $expense->date_time
                ? $expense->date_time->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');

            $expense->receipt_hash = hash(
                'sha256',
                ($expense->invoice_number ?? '').$dateTimeStr.$expense->total_amount
            );
        }
    }

    public function created(Expense $expense): void
    {
        // WhatsApp receipts wait for the batched "Document received" ack before OCR starts.
        if ($expense->status === 'pending' && $expense->source !== 'whatsapp') {
            ExtractReceiptDataJob::dispatch($expense->id);
        }
    }

    public function updated(Expense $expense): void
    {
        if (! $expense->wasChanged('status')) {
            return;
        }

        if (in_array($expense->status, ['parsed', 'reviewed'], true)) {
            // WhatsApp "parsed" alerts run after document parsed/needs-review replies
            // (and after any remaining pending OCR for the same sender).
            $deferForWhatsAppParsed = $expense->source === 'whatsapp'
                && $expense->status === 'parsed';

            if (! $deferForWhatsAppParsed) {
                app(BudgetAlertService::class)->checkAlertsForExpense($expense);
            }
        }

        if ($expense->status === 'requires_manual_review') {
            app(ReceiptManualReviewNotifier::class)->notify($expense);
        }
    }
}
