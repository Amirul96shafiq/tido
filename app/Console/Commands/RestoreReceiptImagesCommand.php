<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RestoreReceiptImagesCommand extends Command
{
    protected $signature = 'receipts:restore-images
        {--source= : Directory containing receipt image files named to match invoice image_path basenames}';

    protected $description = 'Copy missing receipt images from a source folder back into storage for existing invoices';

    public function handle(): int
    {
        $sourceDirectory = rtrim((string) ($this->option('source') ?: base_path('receipts')), DIRECTORY_SEPARATOR);

        if (! is_dir($sourceDirectory)) {
            $this->error("Source directory not found: {$sourceDirectory}");
            $this->line('Pass --source= with the folder that still has the original receipt images.');

            return self::FAILURE;
        }

        $restored = 0;
        $alreadyPresent = 0;
        $missingSource = 0;

        Invoice::query()
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($sourceDirectory, &$restored, &$alreadyPresent, &$missingSource): void {
                $imagePath = (string) $invoice->image_path;

                if (Storage::disk('local')->exists($imagePath) || Storage::exists($imagePath)) {
                    $alreadyPresent++;

                    return;
                }

                $basename = basename($imagePath);
                $sourcePath = $sourceDirectory.DIRECTORY_SEPARATOR.$basename;

                if (! is_file($sourcePath)) {
                    $this->warn("Missing source for invoice #{$invoice->getKey()}: {$basename}");
                    $missingSource++;

                    return;
                }

                $contents = file_get_contents($sourcePath);

                if ($contents === false) {
                    $this->error("Unable to read {$sourcePath}");
                    $missingSource++;

                    return;
                }

                Storage::disk('local')->put($imagePath, $contents);
                $this->info("Restored invoice #{$invoice->getKey()} → {$imagePath}");
                $restored++;
            });

        $this->newLine();
        $this->info("Done. Restored: {$restored}, already present: {$alreadyPresent}, still missing: {$missingSource}");

        return $missingSource > 0 && $restored === 0 ? self::FAILURE : self::SUCCESS;
    }
}
