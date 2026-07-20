<?php

declare(strict_types=1);

use App\Helpers\FilenameDisplay;
use App\Models\Invoice;

test('truncate shortens long filenames to prefix ellipsis and extension', function (): void {
    expect(FilenameDisplay::truncate('wa_ACBF4B3FCAA816DB31A42F65843AA568.jpg'))
        ->toBe('wa_ACBF4B3....jpg')
        ->and(FilenameDisplay::truncate('dashboard_receipt.jpg'))
        ->toBe('dashboard_....jpg');
});

test('truncate leaves short filenames unchanged', function (): void {
    expect(FilenameDisplay::truncate('mock.jpg'))->toBe('mock.jpg');
});

test('truncate handles empty values', function (): void {
    expect(FilenameDisplay::truncate(null))->toBe('')
        ->and(FilenameDisplay::truncate(''))->toBe('');
});

test('label for invoice shows Manual invoice when there is no file', function (): void {
    $invoice = new Invoice([
        'original_filename' => null,
        'image_path' => null,
    ]);

    expect(FilenameDisplay::labelForInvoice($invoice))->toBe('Manual invoice');
});

test('label for invoice truncates real filenames', function (): void {
    $invoice = new Invoice([
        'original_filename' => 'wa_ACBF4B3FCAA816DB31A42F65843AA568.jpg',
        'image_path' => 'receipts/wa_ACBF4B3FCAA816DB31A42F65843AA568.jpg',
    ]);

    expect(FilenameDisplay::labelForInvoice($invoice))->toBe('wa_ACBF4B3....jpg');
});
