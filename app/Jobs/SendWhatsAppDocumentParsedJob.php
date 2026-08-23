<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Services\WhatsAppNotificationService;
use App\Support\ReceiptPipelineLogger;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppPublicUrl;
use App\Support\WhatsAppTypingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class SendWhatsAppDocumentParsedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public function __construct(public int $expenseId)
    {
        $this->onQueue('default');
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
        $startedAt = ReceiptPipelineLogger::start();

        $expense = Expense::find($this->expenseId);

        if (! $expense || $expense->source !== 'whatsapp' || blank($expense->whatsapp_sender)) {
            ReceiptPipelineLogger::completed('receipt.parsed_reply', $startedAt, [
                'expense_id' => $this->expenseId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'ignored',
            ]);

            return;
        }

        $sender = (string) $expense->whatsapp_sender;
        $pendingAck = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));

        if (is_array($pendingAck)) {
            $this->release(1);

            ReceiptPipelineLogger::completed('receipt.parsed_reply', $startedAt, [
                'message_id' => $expense->whatsapp_message_id,
                'expense_id' => $expense->id,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'deferred',
                'reason' => 'received_ack_pending',
            ]);

            return;
        }

        if (Cache::has($this->sentCacheKey())) {
            WhatsAppTypingSession::deactivate($this->expenseId);

            ReceiptPipelineLogger::completed('receipt.parsed_reply', $startedAt, [
                'message_id' => $expense->whatsapp_message_id,
                'expense_id' => $expense->id,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'already_sent',
            ]);

            return;
        }

        $editUrl = WhatsAppPublicUrl::withRoot(
            fn (): string => ExpenseResource::getUrl('edit', ['record' => $expense]),
        );

        $paymentMethod = $expense->paymentMethod?->name;

        $details = [
            'merchant_name' => $expense->merchant_name,
            'total_amount' => $expense->total_amount,
            'currency' => $expense->displayCurrency(),
            'payment_method' => $paymentMethod,
        ];

        $message = $expense->status === 'requires_manual_review'
            ? WhatsAppMessage::documentNeedsReview($editUrl, $details)
            : WhatsAppMessage::documentParsed($editUrl, $details);

        $result = $waService->sendMessageResult(
            $sender,
            $message,
            $expense->whatsapp_message_id,
            $expense->id,
        );

        if (! $result->ok) {
            ReceiptPipelineLogger::completed('receipt.parsed_reply', $startedAt, [
                'message_id' => $expense->whatsapp_message_id,
                'expense_id' => $expense->id,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'failed',
                'reason' => $result->reason,
                'status' => $result->status,
            ]);

            throw new RuntimeException(
                'Unable to send the WhatsApp parsed receipt reply: '.$result->reason,
            );
        }

        Cache::add($this->sentCacheKey(), true, now()->addDay());
        WhatsAppTypingSession::deactivate($this->expenseId);

        if ($expense->status === 'parsed') {
            SendDeferredWhatsAppBudgetAlertJob::dispatch($sender, $expense->id)
                ->delay(now()->addSeconds(2));
        }

        ReceiptPipelineLogger::completed('receipt.parsed_reply', $startedAt, [
            'message_id' => $expense->whatsapp_message_id,
            'expense_id' => $expense->id,
            'queue' => $this->queue ?? 'default',
            'outcome' => 'success',
            'status' => $expense->status,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $expense = Expense::find($this->expenseId);

        if ($expense?->source === 'whatsapp') {
            WhatsAppTypingSession::deactivate($this->expenseId);
        }

        ReceiptPipelineLogger::event('receipt.parsed_reply', [
            'message_id' => $expense?->whatsapp_message_id,
            'expense_id' => $this->expenseId,
            'queue' => $this->queue ?? 'default',
            'outcome' => 'failed',
            'reason' => 'maximum_retries',
        ]);
    }

    protected function sentCacheKey(): string
    {
        return 'wa:document-parsed:sent:'.$this->expenseId;
    }
}
