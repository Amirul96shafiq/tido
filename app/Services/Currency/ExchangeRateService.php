<?php

declare(strict_types=1);

namespace App\Services\Currency;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

final class ExchangeRateService
{
    public function __construct(private readonly ExchangeRateProvider $provider) {}

    /**
     * @return array{
     *     rate: float,
     *     effective_date: string,
     *     fetched_at: string,
     *     provider: string,
     * }
     */
    public function latest(string $baseCurrency, string $targetCurrency): array
    {
        $providerName = (string) config('services.currencyapi.provider', CurrencyApiExchangeRateProvider::NAME);
        $cacheKey = implode(':', [
            'currency-rate',
            $providerName,
            strtoupper($baseCurrency),
            strtoupper($targetCurrency),
            'latest',
        ]);
        $ttl = max(60, (int) config('services.currencyapi.cache_ttl', 86400));

        return Cache::remember(
            $cacheKey,
            now()->addSeconds($ttl),
            fn (): array => $this->provider->latest($baseCurrency, $targetCurrency),
        );
    }

    /**
     * @return array{
     *     rate: float,
     *     effective_date: string,
     *     fetched_at: string,
     *     provider: string,
     * }
     */
    public function rate(
        string $baseCurrency,
        string $targetCurrency,
        CarbonInterface $date,
    ): array {
        $providerName = (string) config('services.currencyapi.provider', CurrencyApiExchangeRateProvider::NAME);
        $cacheKey = implode(':', [
            'currency-rate',
            $providerName,
            strtoupper($baseCurrency),
            strtoupper($targetCurrency),
            $date->toDateString(),
        ]);
        $ttl = max(60, (int) config('services.currencyapi.cache_ttl', 86400));

        return Cache::remember(
            $cacheKey,
            now()->addSeconds($ttl),
            fn (): array => $this->provider->rate($baseCurrency, $targetCurrency, $date),
        );
    }
}
