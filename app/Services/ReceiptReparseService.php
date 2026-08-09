<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Expense;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReceiptReparseService
{
    public function reparse(Expense $invoice): void
    {
        if (blank($invoice->image_path) || ! Storage::exists($invoice->image_path)) {
            throw new RuntimeException("Expense #{$invoice->id} has no readable receipt image.");
        }

        $invoice->expenseItems()->delete();
        $invoice->update([
            'status' => 'pending',
            'currency' => Expense::CURRENCY_UNKNOWN,
            'original_currency' => null,
            'original_total_amount' => null,
            'currency_conversion_status' => Expense::CONVERSION_PENDING,
            'currency_conversion_rate' => null,
            'currency_conversion_date' => null,
            'currency_conversion_provider' => null,
            'currency_conversion_fetched_at' => null,
        ]);

        ExtractReceiptDataJob::dispatch($invoice->id);
    }
}
