<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Services\Currency\CurrencyConversionException;
use App\Services\Currency\CurrencyConversionService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class ManualExpenseCurrencyConversionService
{
    private const CURRENCY_REVIEW_MARKER = '[AI] Currency conversion could not be completed; verify the source amount and rate.';

    public function __construct(private readonly CurrencyConversionService $currencyConversion) {}

    /**
     * @param  array<string, mixed>  $formState
     * @return array<string, mixed>
     */
    public function convert(array $formState): array
    {
        $sourceCurrency = $this->normalizeCurrency($formState['currency'] ?? null);

        if ($sourceCurrency === null) {
            throw new CurrencyConversionException('A valid source currency is required before conversion.');
        }

        if ($sourceCurrency === Expense::CURRENCY_MYR) {
            throw new CurrencyConversionException('A foreign source currency is required before conversion.');
        }

        $dateTime = $this->normalizeDateTime($formState['date_time'] ?? null);
        if ($dateTime === null) {
            throw new CurrencyConversionException('A receipt date is required for foreign-currency conversion.');
        }

        $normalizedItems = [];
        $itemKeys = [];
        $itemsState = is_array($formState['expenseItems'] ?? null) ? $formState['expenseItems'] : [];

        foreach ($itemsState as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemKeys[] = $key;
            $normalizedItems[] = [
                'description' => (string) ($item['description'] ?? ''),
                'quantity' => $this->quantity($item['quantity'] ?? 1),
                'unit_price' => $this->optionalMoney($item['unit_price'] ?? null),
                'line_total' => $this->optionalMoney($item['line_total'] ?? null),
                'serial_number' => $item['serial_number'] ?? null,
                'label' => null,
            ];
        }

        $normalized = [
            'date_time' => $dateTime,
            'subtotal' => $this->requiredMoney($formState['subtotal'] ?? null, 'subtotal'),
            'total_tax' => $this->optionalMoney($formState['total_tax'] ?? null),
            'discount_total' => $this->optionalMoney($formState['discount_total'] ?? null),
            'rounding_amount' => $this->optionalMoney($formState['rounding_amount'] ?? null),
            'total_amount' => $this->requiredMoney($formState['total_amount'] ?? null, 'total amount'),
            'currency' => $sourceCurrency,
            'items' => $normalizedItems,
        ];

        $conversion = $this->currencyConversion->convert($normalized, $dateTime);
        $converted = $conversion['normalized'];
        $metadata = $conversion['metadata'];

        $convertedItems = $itemsState;
        foreach ($itemKeys as $index => $key) {
            $convertedItem = $converted['items'][$index] ?? null;

            if (! is_array($convertedItem) || ! is_array($convertedItems[$key] ?? null)) {
                continue;
            }

            $convertedItems[$key]['unit_price'] = MoneyDisplay::format($convertedItem['unit_price'] ?? 0);
            $convertedItems[$key]['line_total'] = MoneyDisplay::format($convertedItem['line_total'] ?? 0);
        }

        $convertedState = [
            'subtotal' => MoneyDisplay::format($converted['subtotal']),
            'total_tax' => MoneyDisplay::format($converted['total_tax']),
            'discount_total' => MoneyDisplay::format($converted['discount_total']),
            'rounding_amount' => MoneyDisplay::format($converted['rounding_amount']),
            'total_amount' => MoneyDisplay::format($converted['total_amount']),
            'currency' => Expense::CURRENCY_MYR,
            'original_currency' => $metadata['original_currency'],
            'original_total_amount' => MoneyDisplay::format($metadata['original_total_amount']),
            'currency_conversion_status' => $metadata['currency_conversion_status'],
            'currency_conversion_rate' => $metadata['currency_conversion_rate'],
            'currency_conversion_date' => $metadata['currency_conversion_date'],
            'currency_conversion_provider' => $metadata['currency_conversion_provider'],
            'currency_conversion_fetched_at' => $metadata['currency_conversion_fetched_at'],
            'expenseItems' => $convertedItems,
        ];

        $notes = $formState['notes'] ?? null;
        if (is_string($notes)) {
            $notes = trim(str_replace('<p>'.self::CURRENCY_REVIEW_MARKER.'</p>', '', $notes));
            $convertedState['notes'] = $notes === '' ? null : $notes;
        }

        return $convertedState;
    }

    private function normalizeCurrency(mixed $currency): ?string
    {
        if (! is_string($currency) && ! is_numeric($currency)) {
            return null;
        }

        $currency = strtoupper(trim((string) $currency));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    private function normalizeDateTime(mixed $dateTime): ?CarbonInterface
    {
        if ($dateTime instanceof CarbonInterface) {
            return $dateTime;
        }

        if ($dateTime instanceof DateTimeInterface) {
            return Carbon::instance($dateTime);
        }

        if (! is_string($dateTime) || trim($dateTime) === '') {
            return null;
        }

        try {
            return Carbon::parse($dateTime, (string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function requiredMoney(mixed $amount, string $field): float
    {
        $value = $this->moneyValue($amount);

        if ($value === null) {
            throw new CurrencyConversionException("A source {$field} is required before conversion.");
        }

        return $value;
    }

    private function optionalMoney(mixed $amount): float
    {
        return $this->moneyValue($amount) ?? 0.0;
    }

    private function moneyValue(mixed $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (! is_int($amount) && ! is_float($amount) && ! is_string($amount)) {
            return null;
        }

        $value = is_string($amount)
            ? trim(str_replace(',', '', $amount))
            : $amount;

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return is_finite($value) ? $value : null;
    }

    private function quantity(mixed $quantity): float
    {
        if (! is_int($quantity) && ! is_float($quantity) && ! is_string($quantity)) {
            return 1.0;
        }

        $value = is_string($quantity) ? trim(str_replace(',', '', $quantity)) : $quantity;

        if ($value === '' || ! is_numeric($value)) {
            return 1.0;
        }

        $value = (float) $value;

        return $value > 0 && is_finite($value) ? $value : 1.0;
    }
}
