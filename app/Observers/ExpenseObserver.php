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
    /**
     * @var list<string>
     */
    private const SYNC_ATTRIBUTES = [
        'merchant_name',
        'original_filename',
        'status',
        'subtotal',
        'total_tax',
        'total_amount',
        'discount_total',
        'rounding_amount',
        'payment_method_id',
        'source',
        'family_member_id',
        'image_path',
        'date_time',
    ];

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
        $this->broadcastExpense($expense);

        // WhatsApp receipts wait for the batched "Document received" ack before OCR starts.
        if ($expense->status === 'pending' && $expense->source !== 'whatsapp') {
            ExtractReceiptDataJob::dispatch($expense->id);
        }
    }

    public function updated(Expense $expense): void
    {
        if ($expense->wasChanged(self::SYNC_ATTRIBUTES)) {
            $this->broadcastExpense($expense);
        }

        $shouldMatchRecurring = in_array($expense->status, ['parsed', 'reviewed'], true)
            && (
                $expense->wasChanged('status')
                || $expense->wasChanged('date_time')
                || $expense->wasChanged('merchant_name')
                || $expense->wasChanged('document_classification')
                || $expense->wasChanged('family_member_id')
            );

        if ($shouldMatchRecurring) {
            // WhatsApp "parsed" alerts run after document parsed/needs-review replies
            // (and after any remaining pending OCR for the same sender).
            $deferForWhatsAppParsed = $expense->source === 'whatsapp'
                && $expense->status === 'parsed'
                && $expense->wasChanged('status');

            if ($expense->wasChanged('status') && ! $deferForWhatsAppParsed) {
                app(BudgetAlertService::class)->checkAlertsForExpense($expense);
            }

            app(RecurringMatchService::class)->matchExpense($expense);
        }

        if ($expense->wasChanged('status') && $expense->status === 'requires_manual_review') {
            app(ReceiptManualReviewNotifier::class)->notify($expense);
        }
    }

    public function deleted(Expense $expense): void
    {
        $this->broadcastExpense($expense);
    }

    public function restored(Expense $expense): void
    {
        $this->broadcastExpense($expense);
    }

    private function broadcastExpense(Expense $expense): void
    {
        ExpenseUpdated::dispatch($expense->id, (string) $expense->status);
    }
}
