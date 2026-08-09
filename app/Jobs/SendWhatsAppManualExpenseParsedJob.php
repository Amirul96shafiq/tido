<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppManualExpenseReceivedDebouncer;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppPublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendWhatsAppManualExpenseParsedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public function __construct(public int $expenseId)
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
        $invoice = Expense::find($this->expenseId);

        if (! $invoice || $invoice->source !== 'whatsapp' || blank($invoice->whatsapp_sender)) {
            return;
        }

        if (filled($invoice->image_path)) {
            return;
        }

        $sender = (string) $invoice->whatsapp_sender;
        $pendingAck = Cache::get(WhatsAppManualExpenseReceivedDebouncer::cacheKey($sender));

        if (is_array($pendingAck)) {
            $this->release(1);

            return;
        }

        $editUrl = WhatsAppPublicUrl::withRoot(
            fn (): string => ExpenseResource::getUrl('edit', ['record' => $invoice]),
        );

        $paymentMethod = $invoice->paymentMethod?->name;

        $message = WhatsAppMessage::manualExpenseParsed($editUrl, [
            'merchant_name' => $invoice->merchant_name,
            'total_amount' => $invoice->total_amount,
            'payment_method' => $paymentMethod,
        ]);

        $waService->sendMessage($sender, $message);
    }
}
