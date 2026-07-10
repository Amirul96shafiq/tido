<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LabelingType;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Labeling;
use Database\Seeders\LabelingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SeedScannedReceiptsCommand extends Command
{
    protected $signature = 'receipts:seed-scanned {--source= : Directory containing source receipt images}';

    protected $description = 'Seed scanned receipt invoices into storage and the database (idempotent)';

    public function handle(): int
    {
        $this->callSilent('db:seed', [
            '--class' => LabelingSeeder::class,
            '--force' => true,
        ]);

        /** @var list<array<string, mixed>> $receipts */
        $receipts = require database_path('data/scanned_receipts.php');

        $sourceDirectory = rtrim((string) ($this->option('source') ?: base_path('receipts')), DIRECTORY_SEPARATOR);

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($receipts as $receipt) {
            $invoiceNumber = (string) $receipt['invoice_number'];
            $sourceFilename = (string) $receipt['source_filename'];
            $storagePath = 'receipts/'.$sourceFilename;

            if (Invoice::query()->where('invoice_number', $invoiceNumber)->exists()) {
                $this->line("Skipped existing invoice {$invoiceNumber}");
                $skipped++;

                continue;
            }

            try {
                $this->ensureImageInStorage($sourceDirectory, $sourceFilename, $storagePath);
            } catch (\Throwable $exception) {
                $this->error("Failed {$invoiceNumber}: {$exception->getMessage()}");
                $failed++;

                continue;
            }

            try {
                DB::transaction(function () use ($receipt, $invoiceNumber, $sourceFilename, $storagePath, &$created): void {
                    $invoice = Invoice::create([
                        'merchant_name' => $receipt['merchant_name'],
                        'invoice_number' => $invoiceNumber,
                        'date_time' => $receipt['date_time'],
                        'subtotal' => $receipt['subtotal'],
                        'total_tax' => $receipt['total_tax'],
                        'discount_total' => $receipt['discount_total'],
                        'rounding_amount' => $receipt['rounding_amount'],
                        'total_amount' => $receipt['total_amount'],
                        'currency' => 'MYR',
                        'payment_method' => PaymentMethod::from((string) $receipt['payment_method']),
                        'source' => 'manual',
                        'status' => 'reviewed',
                        'image_path' => $storagePath,
                        'original_filename' => $sourceFilename,
                        'notes' => $receipt['notes'],
                    ]);

                    foreach ($receipt['items'] as $item) {
                        $labelingId = Labeling::query()
                            ->where('type', LabelingType::Finance)
                            ->where('slug', $item['labeling_slug'])
                            ->value('id');

                        if ($labelingId === null) {
                            throw new \RuntimeException("Missing labeling slug [{$item['labeling_slug']}]");
                        }

                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'labeling_id' => $labelingId,
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'line_total' => $item['line_total'],
                        ]);
                    }

                    $created++;
                    $this->info("Created invoice {$invoiceNumber} ({$sourceFilename})");
                });
            } catch (\Throwable $exception) {
                $this->error("Failed {$invoiceNumber}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Created: {$created}, skipped: {$skipped}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function ensureImageInStorage(string $sourceDirectory, string $sourceFilename, string $storagePath): void
    {
        if (Storage::exists($storagePath)) {
            return;
        }

        $sourcePath = $sourceDirectory.DIRECTORY_SEPARATOR.$sourceFilename;

        if (! is_file($sourcePath)) {
            throw new \RuntimeException(
                "Image missing in storage [{$storagePath}] and source [{$sourcePath}]. Run while source images still exist, or restore the storage file."
            );
        }

        Storage::put($storagePath, file_get_contents($sourcePath) ?: '');
    }
}
