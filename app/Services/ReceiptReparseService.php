<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Expense;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReceiptReparseService
{
    public function reparse(Expense $expense): void
    {
        if (blank($expense->image_path) || ! Storage::exists($expense->image_path)) {
            throw new RuntimeException("Expense #{$expense->id} has no readable receipt image.");
        }

        $expense->expenseItems()->delete();
        $expense->update([
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

        ExtractReceiptDataJob::dispatch($expense->id);
    }
}
