<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Filament\Support\MoneyStateCast;
use App\Models\Expense;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

final class MoneyDisplay
{
    public const DECIMAL_PLACES = 2;

    public const INPUT_STEP = '0.01';

    public const CURRENCY_CODE = 'MYR';

    public const PREFIX = 'RM';

    public static function format(float|int|string|null $amount): string
    {
        return number_format((float) str_replace(',', '', (string) ($amount ?? 0)), self::DECIMAL_PLACES, '.', ',');
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

    public static function prefixForCurrency(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        if ($currency === self::CURRENCY_CODE) {
            return self::PREFIX;
        }

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'Currency';
    }

    public static function withCurrency(
        float|int|string|null $amount,
        ?string $currency,
        bool $spaceAfterPrefix = true,
    ): string {
        return self::withPrefix($amount, self::prefixForCurrency($currency), $spaceAfterPrefix);
    }

    public static function conversionSummary(Expense $invoice): ?string
    {
        if ($invoice->currency_conversion_status === Expense::CONVERSION_CONVERTED) {
            $rate = $invoice->currency_conversion_rate === null
                ? 'unknown'
                : rtrim(rtrim(number_format((float) $invoice->currency_conversion_rate, 10, '.', ''), '0'), '.');
            $date = $invoice->currency_conversion_date?->format('d M Y') ?? 'unknown date';
            $provider = filled($invoice->currency_conversion_provider)
                ? (string) $invoice->currency_conversion_provider
                : 'exchange-rate provider';

            return sprintf(
                'Converted from %s using rate %s MYR per %s on %s via %s.',
                self::withCurrency($invoice->original_total_amount, $invoice->original_currency),
                $rate,
                (string) ($invoice->original_currency ?? 'foreign currency'),
                $date,
                $provider,
            );
        }

        if ($invoice->currency_conversion_status === Expense::CONVERSION_FAILED) {
            return 'Currency conversion failed; source amount requires manual review.';
        }

        if ($invoice->currency_conversion_status === Expense::CONVERSION_PENDING) {
            return 'Currency conversion pending; source amount is not included in MYR totals.';
        }

        if ($invoice->displayCurrency() !== self::CURRENCY_CODE) {
            return sprintf(
                'Source amount is recorded in %s; conversion is required before MYR totals.',
                (string) ($invoice->displayCurrency() ?? 'an unknown currency'),
            );
        }

        return null;
    }

    public static function configureTextColumn(TextColumn $column): TextColumn
    {
        return $column->money(self::CURRENCY_CODE, decimalPlaces: self::DECIMAL_PLACES);
    }

    public static function validateInputAttribute(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $normalized = str_replace(',', '', (string) $value);

        if (! is_numeric($normalized)) {
            $fail(__('validation.numeric', ['attribute' => $attribute]));

            return;
        }

        if (preg_match('/\.\d{3,}/', $normalized) === 1) {
            $fail(__('validation.decimal', ['attribute' => $attribute, 'decimal' => '0-2']));
        }
    }

    public static function configureTextInput(TextInput $input): TextInput
    {
        return $input
            ->prefix(function (Get $get, ?Model $record): string {
                $currency = $get('currency');

                foreach (['../currency', '../../currency', '../../../currency'] as $path) {
                    if (filled($currency)) {
                        break;
                    }

                    $currency = $get($path);
                }

                if (! filled($currency) && $record instanceof Expense) {
                    $currency = $record->displayCurrency();
                }

                return self::prefixForCurrency($currency ?: self::CURRENCY_CODE);
            })
            ->inputMode('decimal')
            ->step(self::INPUT_STEP)
            ->stateCast(app(MoneyStateCast::class))
            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                MoneyDisplay::validateInputAttribute($attribute, $value, $fail);
            })
            ->live(onBlur: true)
            ->afterStateUpdated(function (Component $component, mixed $state): void {
                if (filled($state)) {
                    $component->state(self::format($state));
                }
            });
    }
}
