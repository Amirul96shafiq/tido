<?php

declare(strict_types=1);

use App\Filament\Support\MoneyStateCast;
use App\Helpers\MoneyDisplay;

test('money state cast formats values for display', function (mixed $input, ?string $expected): void {
    $cast = new MoneyStateCast;

    expect($cast->set($input))->toBe($expected);
})->with([
    [5.4, '5.40'],
    [0, '0.00'],
    ['12.5', '12.50'],
    [null, null],
    ['', null],
]);

test('money state cast parses display values for storage', function (mixed $input, ?float $expected): void {
    $cast = new MoneyStateCast;

    expect($cast->get($input))->toBe($expected);
})->with([
    ['5.40', 5.4],
    ['0.00', 0.0],
    [12.5, 12.5],
    [null, null],
    ['', null],
]);

test('money display round trip preserves amount', function (): void {
    $cast = new MoneyStateCast;
    $display = $cast->set(5.4);

    expect($display)->toBe('5.40')
        ->and($cast->get($display))->toBe(5.4)
        ->and(MoneyDisplay::format($cast->get($display)))->toBe('5.40');
});
