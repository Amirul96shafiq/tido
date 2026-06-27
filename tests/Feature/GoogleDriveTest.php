<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('google drive sync imports images and deletes them from drive', function () {
    \Illuminate\Support\Facades\Queue::fake();

    Storage::fake('local');
    Storage::fake('google');

    Storage::disk('google')->put('receipt_june.png', 'fake-binary-content');
    Storage::disk('google')->put('not_an_image.txt', 'some-text');

    $service = new GoogleDriveService();
    $importedCount = $service->sync();

    expect($importedCount)->toBe(1);

    $invoice = Invoice::first();
    expect($invoice)->not->toBeNull();
    expect($invoice->source)->toBe('google_drive');
    expect($invoice->original_filename)->toBe('receipt_june.png');
    expect(Storage::disk('local')->exists($invoice->image_path))->toBeTrue();

    expect(Storage::disk('google')->exists('receipt_june.png'))->toBeFalse();
});
