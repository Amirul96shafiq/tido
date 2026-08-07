<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReceiptReparseService
{
    public function reparse(Invoice $invoice): void
    {
        if (blank($invoice->image_path) || ! Storage::exists($invoice->image_path)) {
            throw new RuntimeException("Invoice #{$invoice->id} has no readable receipt image.");
        }

        $invoice->invoiceItems()->delete();
        $invoice->update([
            'status' => 'pending',
            'currency' => Invoice::CURRENCY_UNKNOWN,
            'original_currency' => null,
            'original_total_amount' => null,
            'currency_conversion_status' => Invoice::CONVERSION_PENDING,
            'currency_conversion_rate' => null,
            'currency_conversion_date' => null,
            'currency_conversion_provider' => null,
            'currency_conversion_fetched_at' => null,
        ]);

        ExtractReceiptDataJob::dispatch($invoice->id);
    }
}
