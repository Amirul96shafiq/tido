<?php

declare(strict_types=1);

use App\Helpers\MoneyDisplay;

test('format always shows two decimal places', function (float|int|string|null $amount, string $expected): void {
    expect(MoneyDisplay::format($amount))->toBe($expected);
})->with([
    [1.2, '1.20'],
    [1.23, '1.23'],
    [10, '10.00'],
    [0, '0.00'],
    [null, '0.00'],
    ['5.4', '5.40'],
    [1234.5, '1234.50'],
]);

test('parse converts formatted money back to float', function (): void {
    expect(MoneyDisplay::parse('12.50'))->toBe(12.5)
        ->and(MoneyDisplay::parse('1,234.50'))->toBe(1234.5)
        ->and(MoneyDisplay::parse(null))->toBeNull()
        ->and(MoneyDisplay::parse(''))->toBeNull();
});

test('withPrefix formats money with RM prefix', function (): void {
    expect(MoneyDisplay::withPrefix(12.5))->toBe('RM 12.50')
        ->and(MoneyDisplay::withPrefix(12.5, spaceAfterPrefix: false))->toBe('RM12.50');
});
