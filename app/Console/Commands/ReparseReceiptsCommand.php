<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\ReceiptReparseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReparseReceiptsCommand extends Command
{
    protected $signature = 'receipts:reparse
        {invoice? : Invoice ID to reparse}
        {--all : Reparse all eligible invoices with images}
        {--dry-run : List targets without queueing}';

    protected $description = 'Reset invoice OCR state and re-queue ExtractReceiptDataJob';

    public function handle(ReceiptReparseService $reparseService): int
    {
        $invoiceId = $this->argument('invoice');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if ($invoiceId === null && ! $all) {
            $this->error('Pass an invoice ID or use --all.');

            return self::FAILURE;
        }

        if ($invoiceId !== null && $all) {
            $this->error('Pass either an invoice ID or --all, not both.');

            return self::FAILURE;
        }

        $query = Invoice::query()
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '');

        if ($invoiceId !== null) {
            $query->whereKey($invoiceId);
        } else {
            $query->whereIn('status', ['parsed', 'requires_manual_review', 'failed']);
        }

        $invoices = $query->orderBy('id')->get();

        if ($invoices->isEmpty()) {
            $this->warn('No matching invoices found.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            if (! Storage::exists((string) $invoice->image_path)) {
                $this->warn("Skipping invoice #{$invoice->id}: image missing ({$invoice->image_path})");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("Would reparse invoice #{$invoice->id} [{$invoice->status}] {$invoice->merchant_name}");
                $queued++;

                continue;
            }

            try {
                $reparseService->reparse($invoice);
                $this->info("Queued reparse for invoice #{$invoice->id}");
                $queued++;
            } catch (\Throwable $e) {
                $this->error("Failed invoice #{$invoice->id}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run targets: ' : 'Queued: ').$queued.", skipped: {$skipped}");

        return $skipped > 0 && $queued === 0 ? self::FAILURE : self::SUCCESS;
    }
}
