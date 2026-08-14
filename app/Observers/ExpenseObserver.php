<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ExpenseUpdated;
use App\Jobs\ExtractReceiptDataJob;
use App\Models\Expense;
use App\Services\BudgetAlertService;
use App\Services\ReceiptManualReviewNotifier;
use App\Services\RecurringMatchService;

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
        ExpenseUpdated::dispatch($expense->id, (string) $expense->status);

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

        ExpenseUpdated::dispatch($expense->id, (string) $expense->status);

        if (in_array($expense->status, ['parsed', 'reviewed'], true)) {
            // WhatsApp "parsed" alerts run after document parsed/needs-review replies
            // (and after any remaining pending OCR for the same sender).
            $deferForWhatsAppParsed = $expense->source === 'whatsapp'
                && $expense->status === 'parsed';

            if (! $deferForWhatsAppParsed) {
                app(BudgetAlertService::class)->checkAlertsForExpense($expense);
            }

            app(RecurringMatchService::class)->matchExpense($expense);
        }

        if ($expense->status === 'requires_manual_review') {
            app(ReceiptManualReviewNotifier::class)->notify($expense);
        }
    }
}
