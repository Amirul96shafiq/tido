<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Expense;
use App\Services\ReceiptCurrencyBackfillService;
use Illuminate\Console\Command;

class ConvertReceiptCurrencyCommand extends Command
{
    protected $signature = 'receipts:convert-currency
        {expense? : Expense ID to convert}
        {--all : Convert all expenses with non-canonical currency data}
        {--source-currency= : Explicit source ISO currency for a targeted legacy expense}
        {--rate= : Explicit source-currency to MYR rate for a targeted offline correction}
        {--dry-run : List targets without requesting rates or changing data}';

    protected $description = 'Convert stored foreign-currency receipt amounts into canonical MYR values';

    public function handle(ReceiptCurrencyBackfillService $backfillService): int
    {
        $expenseId = $this->argument('expense');
        $all = (bool) $this->option('all');
        $sourceCurrency = $this->option('source-currency');
        $rate = $this->option('rate');
        $dryRun = (bool) $this->option('dry-run');

        if ($expenseId === null && ! $all) {
            $this->error('Pass an expense ID or use --all.');

            return self::FAILURE;
        }

        if ($expenseId !== null && $all) {
            $this->error('Pass either an expense ID or --all, not both.');

            return self::FAILURE;
        }

        if ($sourceCurrency !== null) {
            $sourceCurrency = strtoupper(trim((string) $sourceCurrency));

            if (preg_match('/^[A-Z]{3}$/', $sourceCurrency) !== 1) {
                $this->error('The source currency must be a three-letter ISO code.');

                return self::FAILURE;
            }

            if ($all) {
                $this->error('--source-currency requires one targeted expense ID.');

                return self::FAILURE;
            }
        }

        if ($rate !== null) {
            if ($expenseId === null || $sourceCurrency === null) {
                $this->error('--rate requires one expense ID and --source-currency.');

                return self::FAILURE;
            }

            if (! is_numeric($rate) || ! is_finite((float) $rate) || (float) $rate <= 0) {
                $this->error('The rate must be a positive number.');

                return self::FAILURE;
            }

            $rate = (float) $rate;
        }

        $query = Expense::query()
            ->whereNotNull('raw_ai_response')
            ->orderBy('id');

        if ($expenseId !== null) {
            $query
                ->whereKey($expenseId)
                ->when($sourceCurrency === null, function ($query): void {
                    $query->where(function ($query): void {
                        $query
                            ->where('currency', '!=', Expense::CURRENCY_MYR)
                            ->orWhereNotIn('currency_conversion_status', Expense::CANONICAL_CONVERSION_STATUSES);
                    });
                });
        } else {
            $query->where(function ($query): void {
                $query
                    ->where('currency', '!=', Expense::CURRENCY_MYR)
                    ->orWhereNotIn('currency_conversion_status', Expense::CANONICAL_CONVERSION_STATUSES);
            });
        }

        $expenses = $query->get();
        if ($expenses->isEmpty()) {
            $this->warn('No matching expenses found.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($expenses as $expense) {
            $currency = $sourceCurrency ?? (filled($expense->original_currency)
                ? (string) $expense->original_currency
                : (string) $expense->currency);
            $amount = filled($expense->original_total_amount)
                ? (string) $expense->original_total_amount
                : (string) $expense->total_amount;

            if ($dryRun) {
                $this->line(sprintf(
                    'Would convert expense #%d: %s %s (%s) [%s]%s',
                    $expense->id,
                    filled($currency) ? $currency : Expense::CURRENCY_UNKNOWN,
                    filled($amount) ? $amount : '0.00',
                    $expense->merchant_name,
                    $expense->currency_conversion_status,
                    $rate === null ? '' : sprintf(' at %s MYR', $rate),
                ));
                $processed++;

                continue;
            }

            if ($backfillService->convert($expense, $sourceCurrency, $rate)) {
                $this->info("Converted expense #{$expense->id} to MYR.");
                $processed++;
            } else {
                $this->error("Expense #{$expense->id} requires manual review.");
                $failed++;
            }
        }

        $label = $dryRun ? 'Dry-run targets' : 'Converted';
        $this->newLine();
        $this->info("{$label}: {$processed}, failed: {$failed}");

        return $failed > 0 && $processed === 0 ? self::FAILURE : self::SUCCESS;
    }
}
