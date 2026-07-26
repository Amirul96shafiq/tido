<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\BudgetAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeferredWhatsAppBudgetAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public function __construct(
        public string $senderNumber,
        public int $invoiceId,
    ) {
        $this->onQueue('whatsapp');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [2, 3, 5];
    }

    public function handle(BudgetAlertService $budgetAlerts): void
    {
        $sender = trim($this->senderNumber);

        if ($sender === '') {
            return;
        }

        $pendingExists = Invoice::query()
            ->where('source', 'whatsapp')
            ->where('whatsapp_sender', $sender)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            $this->release(3);

            return;
        }

        $invoice = Invoice::query()->find($this->invoiceId);

        if ($invoice === null || $invoice->source !== 'whatsapp' || $invoice->status !== 'parsed') {
            return;
        }

        $budgetAlerts->checkAlertsForInvoice($invoice);
    }
}
