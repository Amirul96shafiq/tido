<?php

declare(strict_types=1);

use App\Helpers\FilenameDisplay;

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
