<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Expense;
use App\Services\ReceiptReparseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReparseReceiptsCommand extends Command
{
    protected $signature = 'receipts:reparse
        {expense? : Expense ID to reparse}
        {--all : Reparse all eligible expenses with images}
        {--dry-run : List targets without queueing}';

    protected $description = 'Reset expense OCR state and re-queue ExtractReceiptDataJob';

    public function handle(ReceiptReparseService $reparseService): int
    {
        $expenseId = $this->argument('expense');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if ($expenseId === null && ! $all) {
            $this->error('Pass an expense ID or use --all.');

            return self::FAILURE;
        }

        if ($expenseId !== null && $all) {
            $this->error('Pass either an expense ID or --all, not both.');

            return self::FAILURE;
        }

        $query = Expense::query()
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '');

        if ($expenseId !== null) {
            $query->whereKey($expenseId);
        } else {
            $query->whereIn('status', ['parsed', 'requires_manual_review', 'failed']);
        }

        $expenses = $query->orderBy('id')->get();

        if ($expenses->isEmpty()) {
            $this->warn('No matching expenses found.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($expenses as $expense) {
            if (! Storage::exists((string) $expense->image_path)) {
                $this->warn("Skipping expense #{$expense->id}: image missing ({$expense->image_path})");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("Would reparse expense #{$expense->id} [{$expense->status}] {$expense->merchant_name}");
                $queued++;

                continue;
            }

            try {
                $reparseService->reparse($expense);
                $this->info("Queued reparse for expense #{$expense->id}");
                $queued++;
            } catch (\Throwable $e) {
                $this->error("Failed expense #{$expense->id}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run targets: ' : 'Queued: ').$queued.", skipped: {$skipped}");

        return $skipped > 0 && $queued === 0 ? self::FAILURE : self::SUCCESS;
    }
}
