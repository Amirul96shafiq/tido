<?php

declare(strict_types=1);

namespace App\Services\Currency;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ExchangeRateService
{
    private const LAST_GOOD_TTL_SECONDS = 2_592_000; // 30 days

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

        return $this->rememberWithLastGood(
            $cacheKey,
            $ttl,
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

        return $this->rememberWithLastGood(
            $cacheKey,
            $ttl,
            fn (): array => $this->provider->rate($baseCurrency, $targetCurrency, $date),
        );
    }

    /**
     * @param  callable(): array{
     *     rate: float,
     *     effective_date: string,
     *     fetched_at: string,
     *     provider: string,
     * }  $resolver
     * @return array{
     *     rate: float,
     *     effective_date: string,
     *     fetched_at: string,
     *     provider: string,
     * }
     */
    private function rememberWithLastGood(string $cacheKey, int $ttl, callable $resolver): array
    {
        $lastGoodKey = $cacheKey.':last-good';

        try {
            /** @var array{rate: float, effective_date: string, fetched_at: string, provider: string} $result */
            $result = Cache::remember(
                $cacheKey,
                now()->addSeconds($ttl),
                $resolver,
            );

            if ($this->isUsableRate($result)) {
                Cache::put($lastGoodKey, $result, now()->addSeconds(self::LAST_GOOD_TTL_SECONDS));
            }

            return $result;
        } catch (CurrencyConversionException $exception) {
            $fallback = Cache::get($lastGoodKey);

            if ($this->isUsableRate($fallback)) {
                return $fallback;
            }

            throw $exception;
        } catch (Throwable $exception) {
            $fallback = Cache::get($lastGoodKey);

            if ($this->isUsableRate($fallback)) {
                return $fallback;
            }

            throw $exception;
        }
    }

    /**
     * @phpstan-assert-if-true array{rate: float|int|string, effective_date: string, fetched_at: string, provider: string} $value
     */
    private function isUsableRate(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $rate = $value['rate'] ?? null;

        return is_numeric($rate)
            && is_finite((float) $rate)
            && (float) $rate > 0
            && isset($value['effective_date'], $value['fetched_at'], $value['provider'])
            && is_string($value['effective_date'])
            && is_string($value['fetched_at'])
            && is_string($value['provider']);
    }
}
