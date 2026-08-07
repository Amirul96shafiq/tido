<?php

declare(strict_types=1);

namespace App\Services\Currency;

use Carbon\CarbonInterface;

final class CurrencyConversionService
{
    public const MYR = 'MYR';

    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{
     *     normalized: array<string, mixed>,
     *     metadata: array{
     *         original_currency: string,
     *         original_total_amount: float,
     *         currency_conversion_status: string,
     *         currency_conversion_rate: ?float,
     *         currency_conversion_date: ?string,
     *         currency_conversion_provider: ?string,
     *         currency_conversion_fetched_at: ?string,
     *     },
     * }
     */
    public function convert(
        array $normalized,
        ?CarbonInterface $dateTime,
        ?float $rateOverride = null,
    ): array {
        $currency = $normalized['currency'] ?? null;

        if (! is_string($currency) || ! $this->isCurrencyCode($currency)) {
            throw new CurrencyConversionException('The receipt currency could not be determined confidently.');
        }

        $currency = strtoupper($currency);
        $originalTotal = (float) ($normalized['total_amount'] ?? 0);

        if ($currency === self::MYR) {
            return [
                'normalized' => $normalized,
                'metadata' => [
                    'original_currency' => self::MYR,
                    'original_total_amount' => $originalTotal,
                    'currency_conversion_status' => 'not_required',
                    'currency_conversion_rate' => null,
                    'currency_conversion_date' => null,
                    'currency_conversion_provider' => null,
                    'currency_conversion_fetched_at' => null,
                ],
            ];
        }

        if ($dateTime === null) {
            throw new CurrencyConversionException('A receipt date is required for foreign-currency conversion.');
        }

        $rateDetails = $rateOverride === null
            ? $this->exchangeRates->rate($currency, self::MYR, $dateTime)
            : [
                'rate' => $rateOverride,
                'effective_date' => $dateTime->toDateString(),
                'fetched_at' => now()->toDateTimeString(),
                'provider' => 'receipt_printed_rate',
            ];
        $rate = (float) $rateDetails['rate'];

        if (! is_finite($rate) || $rate <= 0) {
            throw new CurrencyConversionException('The exchange-rate provider returned an unusable rate.');
        }

        $converted = $normalized;
        foreach (['subtotal', 'total_tax', 'discount_total', 'rounding_amount', 'total_amount'] as $field) {
            $converted[$field] = $this->convertMoney((float) ($normalized[$field] ?? 0), $rate);
        }

        $convertedItems = [];
        foreach ($normalized['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $item['unit_price'] = $this->convertMoney((float) ($item['unit_price'] ?? 0), $rate);
            $item['line_total'] = $this->convertMoney((float) ($item['line_total'] ?? 0), $rate);
            $convertedItems[] = $item;
        }
        $converted['items'] = $convertedItems;
        $converted['currency'] = self::MYR;

        return [
            'normalized' => $converted,
            'metadata' => [
                'original_currency' => $currency,
                'original_total_amount' => $originalTotal,
                'currency_conversion_status' => 'converted',
                'currency_conversion_rate' => $rate,
                'currency_conversion_date' => $rateDetails['effective_date'],
                'currency_conversion_provider' => $rateDetails['provider'],
                'currency_conversion_fetched_at' => $rateDetails['fetched_at'],
            ],
        ];
    }

    private function convertMoney(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }

    private function isCurrencyCode(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', strtoupper(trim($currency))) === 1;
    }
}
