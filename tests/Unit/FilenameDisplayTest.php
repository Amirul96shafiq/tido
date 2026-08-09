<?php

declare(strict_types=1);

use App\Helpers\FilenameDisplay;
use App\Models\Expense;

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

test('label for invoice shows Manual expense when there is no file', function (): void {
    $invoice = new Expense([
        'original_filename' => null,
        'image_path' => null,
    ]);

    expect(FilenameDisplay::labelForExpense($invoice))->toBe('Manual expense');
});

test('label for invoice truncates real filenames', function (): void {
    $invoice = new Expense([
        'original_filename' => 'wa_ACBF4B3FCAA816DB31A42F65843AA568.jpg',
        'image_path' => 'receipts/wa_ACBF4B3FCAA816DB31A42F65843AA568.jpg',
    ]);

    expect(FilenameDisplay::labelForExpense($invoice))->toBe('wa_ACBF4B3....jpg');
});
