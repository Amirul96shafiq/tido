<?php

declare(strict_types=1);

use App\Models\Expense;
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

test('receipts seed scanned creates reviewed expenses with storage images', function () {
    $exitCode = Artisan::call('receipts:seed-scanned', [
        '--source' => $this->sourceDir,
    ]);

    expect($exitCode)->toBe(0);
    expect(Expense::query()->count())->toBe(count($this->fixture));

    $first = $this->fixture[0];
    $expense = Expense::query()->where('invoice_number', $first['invoice_number'])->first();

    expect($expense)->not->toBeNull();
    expect($expense->status)->toBe('reviewed');
    expect($expense->source)->toBe('manual');
    expect($expense->image_path)->toBe('receipts/'.$first['source_filename']);
    expect($expense->original_filename)->toBe($first['source_filename']);
    expect($expense->paymentMethod->slug)->toBe($first['payment_method']);
    expect($expense->expenseItems)->toHaveCount(count($first['items']));
    expect(Storage::exists($expense->image_path))->toBeTrue();
});

test('receipts seed scanned is idempotent', function () {
    Artisan::call('receipts:seed-scanned', ['--source' => $this->sourceDir]);
    $firstCount = Expense::query()->count();

    $exitCode = Artisan::call('receipts:seed-scanned', ['--source' => $this->sourceDir]);
    $secondCount = Expense::query()->count();

    expect($exitCode)->toBe(0);
    expect($firstCount)->toBe(count($this->fixture));
    expect($secondCount)->toBe($firstCount);
});
