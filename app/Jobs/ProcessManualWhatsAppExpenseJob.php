<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Support\ExpenseSenderAttribution;
use App\Support\ManualWhatsAppExpenseParser;
use App\Support\WhatsAppManualExpenseReceivedDebouncer;
use App\Support\WhatsAppProcessingJobKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessManualWhatsAppExpenseJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor;

    public function __construct(
        public string $senderNumber,
        public string $text,
        public string $messageId,
    ) {
        $this->onQueue('whatsapp');
        $this->uniqueFor = WhatsAppProcessingJobKey::uniqueForSeconds();
    }

    public function uniqueId(): string
    {
        return WhatsAppProcessingJobKey::forMessage($this->messageId, 'manual-expense');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->expireAfter(120)
                ->releaseAfter(10),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(): void
    {
        if (WhatsAppProcessingJobKey::manualExpenseAlreadyCreated($this->messageId)) {
            Log::info('ProcessManualWhatsAppExpenseJob skipped duplicate message', [
                'message_id' => $this->messageId,
                'sender' => $this->senderNumber,
            ]);

            return;
        }

        $blocks = ManualWhatsAppExpenseParser::parse($this->text);

        if ($blocks === []) {
            Log::info('ProcessManualWhatsAppExpenseJob skipped: text did not parse', [
                'sender' => $this->senderNumber,
                'message_id' => $this->messageId,
            ]);

            return;
        }

        DB::transaction(function () use ($blocks): void {
            foreach ($blocks as $blockIndex => $block) {
                $items = $block['items'];
                $totalAmount = round(array_sum(array_column($items, 'line_total')), 2);

                $expense = Expense::create([
                    'merchant_name' => $block['merchant_name'],
                    'date_time' => now(),
                    'subtotal' => $totalAmount,
                    'total_tax' => 0.00,
                    'discount_total' => 0.00,
                    'rounding_amount' => 0.00,
                    'total_amount' => $totalAmount,
                    'currency' => 'MYR',
                    'payment_method_id' => $block['payment_method']->id,
                    'source' => 'whatsapp',
                    'whatsapp_sender' => $this->senderNumber,
                    'whatsapp_message_id' => WhatsAppProcessingJobKey::messageIdForManualBlock(
                        $this->messageId,
                        $blockIndex,
                    ),
                    'family_member_id' => ExpenseSenderAttribution::familyMemberIdForSender($this->senderNumber),
                    'status' => 'pending',
                    'image_path' => null,
                    'raw_ai_response' => [
                        'manual_whatsapp_text' => $this->text,
                        'parsed_block' => [
                            'merchant_name' => $block['merchant_name'],
                            'payment_method' => $block['payment_method']->slug,
                            'items' => $block['items'],
                        ],
                    ],
                ]);

                foreach ($items as $item) {
                    $quantity = (float) $item['quantity'];
                    $lineTotal = round((float) $item['line_total'], 2);
                    $unitPrice = $quantity != 0.0
                        ? round($lineTotal / $quantity, 2)
                        : 0.00;

                    ExpenseItem::create([
                        'expense_id' => $expense->id,
                        'label_id' => null,
                        'description' => $item['description'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                }

                WhatsAppManualExpenseReceivedDebouncer::register($this->senderNumber, $expense->id);

                Log::info('Manual WhatsApp expense created', [
                    'expense_id' => $expense->id,
                    'message_id' => $expense->whatsapp_message_id,
                    'merchant_name' => $expense->merchant_name,
                    'item_count' => count($items),
                ]);
            }
        });
    }
}
