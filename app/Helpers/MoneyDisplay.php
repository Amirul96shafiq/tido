<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Filament\Support\MoneyStateCast;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\TextColumn;

final class MoneyDisplay
{
    public const DECIMAL_PLACES = 2;

    public const INPUT_STEP = '0.01';

    public const CURRENCY_CODE = 'MYR';

    public const PREFIX = 'RM';

    public static function format(float|int|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), self::DECIMAL_PLACES, '.', '');
    }

    public static function parse(float|int|string|null $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (float) str_replace(',', '', (string) $amount);
    }

    public static function withPrefix(
        float|int|string|null $amount,
        string $prefix = self::PREFIX,
        bool $spaceAfterPrefix = true,
    ): string {
        $formatted = self::format($amount);

        if ($spaceAfterPrefix) {
            return "{$prefix} {$formatted}";
        }

        return "{$prefix}{$formatted}";
    }

    public static function configureTextColumn(TextColumn $column): TextColumn
    {
        return $column->money(self::CURRENCY_CODE, decimalPlaces: self::DECIMAL_PLACES);
    }

    public static function configureTextInput(TextInput $input): TextInput
    {
        return $input
            ->prefix(self::PREFIX)
            ->inputMode('decimal')
            ->step(self::INPUT_STEP)
            ->stateCast(app(MoneyStateCast::class))
            ->rule('numeric')
            ->rule('decimal:0,2')
            ->live(onBlur: true)
            ->afterStateUpdated(function (Component $component, mixed $state): void {
                if (filled($state)) {
                    $component->state(self::format($state));
                }
            });
    }
}
