<?php

declare(strict_types=1);

use App\Models\Invoice;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake();
    $this->seed(LabelSeeder::class);

    $this->sourceDir = storage_path('framework/testing/receipts-source');
    File::deleteDirectory($this->sourceDir);
    File::ensureDirectoryExists($this->sourceDir);

    $this->fixture = require database_path('data/scanned_receipts.php');

    foreach ($this->fixture as $receipt) {
        File::put(
            $this->sourceDir.'/'.$receipt['source_filename'],
            'fake-receipt-bytes',
        );
    }
});

afterEach(function () {
    File::deleteDirectory($this->sourceDir);
});

test('receipts seed scanned creates reviewed invoices with storage images', function () {
    $exitCode = Artisan::call('receipts:seed-scanned', [
        '--source' => $this->sourceDir,
    ]);

    expect($exitCode)->toBe(0);
    expect(Invoice::query()->count())->toBe(count($this->fixture));

    $first = $this->fixture[0];
    $invoice = Invoice::query()->where('invoice_number', $first['invoice_number'])->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->status)->toBe('reviewed');
    expect($invoice->source)->toBe('manual');
    expect($invoice->image_path)->toBe('receipts/'.$first['source_filename']);
    expect($invoice->original_filename)->toBe($first['source_filename']);
    expect($invoice->paymentMethod->slug)->toBe($first['payment_method']);
    expect($invoice->invoiceItems)->toHaveCount(count($first['items']));
    expect(Storage::exists($invoice->image_path))->toBeTrue();
});

test('receipts seed scanned is idempotent', function () {
    Artisan::call('receipts:seed-scanned', ['--source' => $this->sourceDir]);
    $firstCount = Invoice::query()->count();

    $exitCode = Artisan::call('receipts:seed-scanned', ['--source' => $this->sourceDir]);
    $secondCount = Invoice::query()->count();

    expect($exitCode)->toBe(0);
    expect($firstCount)->toBe(count($this->fixture));
    expect($secondCount)->toBe($firstCount);
});
