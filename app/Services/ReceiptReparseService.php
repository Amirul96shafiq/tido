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
        $invoice->update(['status' => 'pending']);

        ExtractReceiptDataJob::dispatch($invoice->id);
    }
}
