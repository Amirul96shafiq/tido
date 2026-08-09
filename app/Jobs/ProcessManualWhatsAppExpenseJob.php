<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Support\ExpenseSenderAttribution;
use App\Support\ManualWhatsAppExpenseParser;
use App\Support\WhatsAppManualExpenseReceivedDebouncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessManualWhatsAppExpenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $senderNumber,
        public string $text,
    ) {
        $this->onQueue('whatsapp');
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
        $blocks = ManualWhatsAppExpenseParser::parse($this->text);

        if ($blocks === []) {
            Log::info('ProcessManualWhatsAppExpenseJob skipped: text did not parse', [
                'sender' => $this->senderNumber,
            ]);

            return;
        }

        foreach ($blocks as $block) {
            $items = $block['items'];
            $totalAmount = round(array_sum(array_column($items, 'line_total')), 2);

            $invoice = Expense::create([
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
                    'expense_id' => $invoice->id,
                    'label_id' => null,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
            }

            WhatsAppManualExpenseReceivedDebouncer::register($this->senderNumber, $invoice->id);

            Log::info('Manual WhatsApp invoice created', [
                'expense_id' => $invoice->id,
                'merchant_name' => $invoice->merchant_name,
                'item_count' => count($items),
            ]);
        }
    }
}
