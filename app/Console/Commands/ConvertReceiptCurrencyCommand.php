<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\ReceiptCurrencyBackfillService;
use Illuminate\Console\Command;

class ConvertReceiptCurrencyCommand extends Command
{
    protected $signature = 'receipts:convert-currency
        {invoice? : Invoice ID to convert}
        {--all : Convert all invoices with non-canonical currency data}
        {--source-currency= : Explicit source ISO currency for a targeted legacy invoice}
        {--rate= : Explicit source-currency to MYR rate for a targeted offline correction}
        {--dry-run : List targets without requesting rates or changing data}';

    protected $description = 'Convert stored foreign-currency receipt amounts into canonical MYR values';

    public function handle(ReceiptCurrencyBackfillService $backfillService): int
    {
        $invoiceId = $this->argument('invoice');
        $all = (bool) $this->option('all');
        $sourceCurrency = $this->option('source-currency');
        $rate = $this->option('rate');
        $dryRun = (bool) $this->option('dry-run');

        if ($invoiceId === null && ! $all) {
            $this->error('Pass an invoice ID or use --all.');

            return self::FAILURE;
        }

        if ($invoiceId !== null && $all) {
            $this->error('Pass either an invoice ID or --all, not both.');

            return self::FAILURE;
        }

        if ($sourceCurrency !== null) {
            $sourceCurrency = strtoupper(trim((string) $sourceCurrency));

            if (preg_match('/^[A-Z]{3}$/', $sourceCurrency) !== 1) {
                $this->error('The source currency must be a three-letter ISO code.');

                return self::FAILURE;
            }

            if ($all) {
                $this->error('--source-currency requires one targeted invoice ID.');

                return self::FAILURE;
            }
        }

        if ($rate !== null) {
            if ($invoiceId === null || $sourceCurrency === null) {
                $this->error('--rate requires one invoice ID and --source-currency.');

                return self::FAILURE;
            }

            if (! is_numeric($rate) || ! is_finite((float) $rate) || (float) $rate <= 0) {
                $this->error('The rate must be a positive number.');

                return self::FAILURE;
            }

            $rate = (float) $rate;
        }

        $query = Invoice::query()
            ->whereNotNull('raw_ai_response')
            ->orderBy('id');

        if ($invoiceId !== null) {
            $query
                ->whereKey($invoiceId)
                ->when($sourceCurrency === null, function ($query): void {
                    $query->where(function ($query): void {
                        $query
                            ->where('currency', '!=', Invoice::CURRENCY_MYR)
                            ->orWhereNotIn('currency_conversion_status', Invoice::CANONICAL_CONVERSION_STATUSES);
                    });
                });
        } else {
            $query->where(function ($query): void {
                $query
                    ->where('currency', '!=', Invoice::CURRENCY_MYR)
                    ->orWhereNotIn('currency_conversion_status', Invoice::CANONICAL_CONVERSION_STATUSES);
            });
        }

        $invoices = $query->get();
        if ($invoices->isEmpty()) {
            $this->warn('No matching invoices found.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $currency = $sourceCurrency ?? (filled($invoice->original_currency)
                ? (string) $invoice->original_currency
                : (string) $invoice->currency);
            $amount = filled($invoice->original_total_amount)
                ? (string) $invoice->original_total_amount
                : (string) $invoice->total_amount;

            if ($dryRun) {
                $this->line(sprintf(
                    'Would convert invoice #%d: %s %s (%s) [%s]%s',
                    $invoice->id,
                    filled($currency) ? $currency : Invoice::CURRENCY_UNKNOWN,
                    filled($amount) ? $amount : '0.00',
                    $invoice->merchant_name,
                    $invoice->currency_conversion_status,
                    $rate === null ? '' : sprintf(' at %s MYR', $rate),
                ));
                $processed++;

                continue;
            }

            if ($backfillService->convert($invoice, $sourceCurrency, $rate)) {
                $this->info("Converted invoice #{$invoice->id} to MYR.");
                $processed++;
            } else {
                $this->error("Invoice #{$invoice->id} requires manual review.");
                $failed++;
            }
        }

        $label = $dryRun ? 'Dry-run targets' : 'Converted';
        $this->newLine();
        $this->info("{$label}: {$processed}, failed: {$failed}");

        return $failed > 0 && $processed === 0 ? self::FAILURE : self::SUCCESS;
    }
}
