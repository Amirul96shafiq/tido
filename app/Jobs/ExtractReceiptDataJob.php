<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LabelingType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Labeling;
use App\Prompts\ReceiptExtractionPrompt;
use App\Services\OllamaService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExtractReceiptDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(public int $invoiceId) {}

    public function handle(OllamaService $ollama): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || $invoice->status !== 'pending') {
            return;
        }

        if (empty($invoice->image_path) || ! Storage::exists($invoice->image_path)) {
            Log::error('Invoice image path does not exist', ['invoice_id' => $this->invoiceId]);
            $invoice->update(['status' => 'failed']);

            return;
        }

        $imageContents = Storage::get($invoice->image_path);
        $base64Image = base64_encode($imageContents);

        $parsed = $ollama->parseReceipt($base64Image, ReceiptExtractionPrompt::get());

        if (! $parsed) {
            throw new \Exception('Ollama receipt extraction returned empty or invalid response.');
        }

        $invoice->merchant_name = $parsed['merchant_name'] ?? 'Unknown Merchant';
        $invoice->invoice_number = $parsed['invoice_number'] ?? null;

        if (! empty($parsed['date_time'])) {
            try {
                $invoice->date_time = Carbon::parse($parsed['date_time']);
            } catch (\Throwable) {
                $invoice->date_time = now();
            }
        } else {
            $invoice->date_time = now();
        }

        $invoice->subtotal = (float) ($parsed['subtotal'] ?? 0.00);
        $invoice->total_tax = (float) ($parsed['total_tax'] ?? 0.00);
        $invoice->total_amount = (float) ($parsed['total_amount'] ?? 0.00);
        $invoice->currency = $parsed['currency'] ?? 'MYR';
        $invoice->raw_ai_response = $parsed;
        $invoice->status = 'parsed';
        $invoice->save();

        if (! empty($parsed['items']) && is_array($parsed['items'])) {
            foreach ($parsed['items'] as $item) {
                $suggestedCat = $item['suggested_category'] ?? null;
                $labelingId = null;

                if ($suggestedCat) {
                    $slug = Str::slug($suggestedCat);
                    $labelingId = Labeling::query()
                        ->where('type', LabelingType::Finance)
                        ->where('slug', $slug)
                        ->first()?->id;
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'labeling_id' => $labelingId,
                    'description' => $item['description'] ?? 'Line Item',
                    'quantity' => (float) ($item['quantity'] ?? 1.000),
                    'unit_price' => (float) ($item['unit_price'] ?? 0.00),
                    'line_total' => (float) ($item['line_total'] ?? 0.00),
                ]);
            }
        }

        Log::info('Invoice parsed successfully via AI pipeline', ['invoice_id' => $invoice->id]);
    }

    public function failed(\Throwable $exception): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if ($invoice) {
            $invoice->update(['status' => 'requires_manual_review']);
        }

        Log::error('ExtractReceiptDataJob failed after maximum retries', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
