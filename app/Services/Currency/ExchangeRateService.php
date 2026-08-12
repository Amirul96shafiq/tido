<?php

declare(strict_types=1);

namespace App\Services\Currency;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ExchangeRateService
{
    private const LAST_GOOD_TTL_SECONDS = 2_592_000; // 30 days

    private const SERIES_UNAVAILABLE_TTL_SECONDS = 60;

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
        $cacheKey = $this->latestCacheKey($baseCurrency, $targetCurrency);
        $ttl = max(60, (int) config('services.currencyapi.cache_ttl', 86400));

        return $this->rememberWithLastGood(
            $cacheKey,
            $ttl,
            fn (): array => $this->provider->latest($baseCurrency, $targetCurrency),
        );
    }

    /**
     * Read-only dashboard path: never calls CurrencyAPI.
     *
     * @return array{
     *     rate: float,
     *     effective_date: string,
     *     fetched_at: string,
     *     provider: string,
     * }
     */
    public function cachedLatest(string $baseCurrency, string $targetCurrency): array
    {
        $cacheKey = $this->latestCacheKey($baseCurrency, $targetCurrency);
        $cached = Cache::get($cacheKey);

        if ($this->isUsableRate($cached)) {
            return $cached;
        }

        $fallback = Cache::get($cacheKey.':last-good');

        if ($this->isUsableRate($fallback)) {
            return $fallback;
        }

        throw new CurrencyConversionException(
            'No cached exchange rate is available. Run currency:refresh-rates.',
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
        // Historical endpoint rejects the incomplete UTC current day (and future dates).
        if ($date->toDateString() > $this->latestCompletableHistoricalDateString()) {
            return $this->latest($baseCurrency, $targetCurrency);
        }

        $cacheKey = $this->historicalCacheKey($baseCurrency, $targetCurrency, $date);
        $ttl = max(60, (int) config('services.currencyapi.cache_ttl', 86400));

        return $this->rememberWithLastGood(
            $cacheKey,
            $ttl,
            fn (): array => $this->provider->rate($baseCurrency, $targetCurrency, $date),
        );
    }

    /**
     * @return list<array{date: string, rate: float}>
     */
    public function series(string $baseCurrency, string $targetCurrency, int $days = 30): array
    {
        $days = max(1, $days);
        $maxPoints = max(1, (int) config('services.currencyapi.series_max_points', 7));
        $cacheKey = $this->seriesCacheKey($baseCurrency, $targetCurrency, $days, $maxPoints);
        $ttl = max(60, (int) config('services.currencyapi.series_cache_ttl', config('services.currencyapi.cache_ttl', 86400)));
        $lastGoodKey = $cacheKey.':last-good';
        $unavailableKey = $cacheKey.':unavailable';
        $cached = Cache::get($cacheKey);

        if ($this->isUsableSeries($cached)) {
            /** @var list<array{date: string, rate: float}> $cached */
            return $cached;
        }

        if (Cache::has($unavailableKey)) {
            $fallback = Cache::get($lastGoodKey);

            if ($this->isUsableSeries($fallback)) {
                /** @var list<array{date: string, rate: float}> $fallback */
                return $fallback;
            }

            throw new CurrencyConversionException(
                'No exchange-rate history is available for the requested period.',
            );
        }

        try {
            /** @var list<array{date: string, rate: float}> $points */
            $points = Cache::lock('lock:'.$cacheKey, 30)->block(
                20,
                function () use ($baseCurrency, $targetCurrency, $days, $maxPoints, $cacheKey, $ttl, $lastGoodKey): array {
                    $existing = Cache::get($cacheKey);

                    if ($this->isUsableSeries($existing)) {
                        /** @var list<array{date: string, rate: float}> $existing */
                        return $existing;
                    }

                    return $this->buildSeries(
                        $baseCurrency,
                        $targetCurrency,
                        $days,
                        $maxPoints,
                        $cacheKey,
                        $ttl,
                        $lastGoodKey,
                    );
                },
            );

            return $points;
        } catch (LockTimeoutException $exception) {
            $fallback = Cache::get($lastGoodKey);

            if ($this->isUsableSeries($fallback)) {
                /** @var list<array{date: string, rate: float}> $fallback */
                return $fallback;
            }

            throw new CurrencyConversionException(
                'No exchange-rate history is available for the requested period.',
                previous: $exception,
            );
        } catch (CurrencyConversionException $exception) {
            $fallback = Cache::get($lastGoodKey);

            if ($this->isUsableSeries($fallback)) {
                /** @var list<array{date: string, rate: float}> $fallback */
                return $fallback;
            }

            Cache::put($unavailableKey, true, now()->addSeconds(self::SERIES_UNAVAILABLE_TTL_SECONDS));

            throw $exception;
        } catch (Throwable $exception) {
            $fallback = Cache::get($lastGoodKey);

            if ($this->isUsableSeries($fallback)) {
                /** @var list<array{date: string, rate: float}> $fallback */
                return $fallback;
            }

            Cache::put($unavailableKey, true, now()->addSeconds(self::SERIES_UNAVAILABLE_TTL_SECONDS));

            throw $exception;
        }
    }

    /**
     * Read-only dashboard path: never calls CurrencyAPI.
     *
     * @return list<array{date: string, rate: float}>
     */
    public function cachedSeries(string $baseCurrency, string $targetCurrency, int $days = 30): array
    {
        $days = max(1, $days);
        $maxPoints = max(1, (int) config('services.currencyapi.series_max_points', 7));
        $cacheKey = $this->seriesCacheKey($baseCurrency, $targetCurrency, $days, $maxPoints);
        $cached = Cache::get($cacheKey);

        if ($this->isUsableSeries($cached)) {
            /** @var list<array{date: string, rate: float}> $cached */
            return $cached;
        }

        $fallback = Cache::get($cacheKey.':last-good');

        if ($this->isUsableSeries($fallback)) {
            /** @var list<array{date: string, rate: float}> $fallback */
            return $fallback;
        }

        throw new CurrencyConversionException(
            'No cached exchange-rate history is available. Run currency:refresh-rates.',
        );
    }

    /**
     * Force-refresh dashboard latest + series via CurrencyAPI (scheduler / artisan).
     *
     * Always refreshes latest. Reuses sparkline last-good when present so warm runs
     * cost 0–1 historical calls instead of rebuilding every sample date.
     *
     * @return array{
     *     latest: array{rate: float, effective_date: string, fetched_at: string, provider: string},
     *     series: list<array{date: string, rate: float}>,
     * }
     */
    public function refreshDashboardRates(
        string $baseCurrency = 'USD',
        string $targetCurrency = 'MYR',
        int $days = 30,
    ): array {
        $days = max(1, $days);
        $maxPoints = max(1, (int) config('services.currencyapi.series_max_points', 7));
        $latestKey = $this->latestCacheKey($baseCurrency, $targetCurrency);
        $seriesKey = $this->seriesCacheKey($baseCurrency, $targetCurrency, $days, $maxPoints);
        $lastGoodKey = $seriesKey.':last-good';
        $seriesTtl = max(
            60,
            (int) config(
                'services.currencyapi.series_cache_ttl',
                config('services.currencyapi.cache_ttl', 86400),
            ),
        );

        Cache::forget($latestKey);
        Cache::forget($seriesKey);
        Cache::forget($seriesKey.':unavailable');

        $latest = $this->latest($baseCurrency, $targetCurrency);

        $lastGood = Cache::get($lastGoodKey);
        $series = $this->isUsableSeries($lastGood)
            ? $this->refreshSeriesFromLastGood(
                $baseCurrency,
                $targetCurrency,
                $days,
                $maxPoints,
                $seriesKey,
                $seriesTtl,
                $lastGoodKey,
                $lastGood,
            )
            : $this->buildSeries(
                $baseCurrency,
                $targetCurrency,
                $days,
                $maxPoints,
                $seriesKey,
                $seriesTtl,
                $lastGoodKey,
            );

        return [
            'latest' => $latest,
            'series' => $series,
        ];
    }

    /**
     * @return list<array{date: string, rate: float}>
     */
    private function buildSeries(
        string $baseCurrency,
        string $targetCurrency,
        int $days,
        int $maxPoints,
        string $cacheKey,
        int $ttl,
        string $lastGoodKey,
    ): array {
        $sampleDates = $this->seriesSampleDates($days, $maxPoints);
        $series = [];

        foreach ($sampleDates as $date) {
            try {
                $rateDetails = $this->rate($baseCurrency, $targetCurrency, $date);
            } catch (CurrencyConversionException) {
                continue;
            }

            if (! $this->isUsableRate($rateDetails)) {
                continue;
            }

            $series[] = [
                'date' => $date->toDateString(),
                'rate' => (float) $rateDetails['rate'],
            ];
        }

        if ($series === []) {
            throw new CurrencyConversionException(
                'No exchange-rate history is available for the requested period.',
            );
        }

        Cache::put($cacheKey, $series, now()->addSeconds($ttl));
        Cache::put($lastGoodKey, $series, now()->addSeconds(self::LAST_GOOD_TTL_SECONDS));

        return $series;
    }

    /**
     * Warm sparkline refresh: reuse last-good points, fetch only the UTC tip day when missing.
     *
     * @param  list<array{date: string, rate: float|int|string}>  $lastGood
     * @return list<array{date: string, rate: float}>
     */
    private function refreshSeriesFromLastGood(
        string $baseCurrency,
        string $targetCurrency,
        int $days,
        int $maxPoints,
        string $cacheKey,
        int $ttl,
        string $lastGoodKey,
        array $lastGood,
    ): array {
        $tip = $this->latestCompletableHistoricalDate();
        $tipDate = $tip->toDateString();
        $windowStartDate = $tip->copy()->subDays(max(0, $days - 1))->toDateString();

        $pointsByDate = [];

        foreach ($lastGood as $point) {
            $pointsByDate[(string) $point['date']] = [
                'date' => (string) $point['date'],
                'rate' => (float) $point['rate'],
            ];
        }

        if (! isset($pointsByDate[$tipDate])) {
            try {
                $rateDetails = $this->rate($baseCurrency, $targetCurrency, $tip);

                if ($this->isUsableRate($rateDetails)) {
                    $pointsByDate[$tipDate] = [
                        'date' => $tipDate,
                        'rate' => (float) $rateDetails['rate'],
                    ];
                }
            } catch (CurrencyConversionException) {
                // Keep prior last-good points when tip day cannot be fetched.
            }
        }

        $series = $this->normalizeSeriesWindow(
            array_values($pointsByDate),
            $windowStartDate,
            $tipDate,
            $maxPoints,
        );

        if ($series === []) {
            return $this->buildSeries(
                $baseCurrency,
                $targetCurrency,
                $days,
                $maxPoints,
                $cacheKey,
                $ttl,
                $lastGoodKey,
            );
        }

        if ($this->seriesSpanDays($series) < (int) floor($days / 2)) {
            return $this->buildSeries(
                $baseCurrency,
                $targetCurrency,
                $days,
                $maxPoints,
                $cacheKey,
                $ttl,
                $lastGoodKey,
            );
        }

        Cache::put($cacheKey, $series, now()->addSeconds($ttl));
        Cache::put($lastGoodKey, $series, now()->addSeconds(self::LAST_GOOD_TTL_SECONDS));

        return $series;
    }

    /**
     * @param  list<array{date: string, rate: float}>  $series
     * @return list<array{date: string, rate: float}>
     */
    private function normalizeSeriesWindow(
        array $series,
        string $windowStartDate,
        string $windowEndDate,
        int $maxPoints,
    ): array {
        $filtered = array_values(array_filter(
            $series,
            static fn (array $point): bool => $point['date'] >= $windowStartDate
                && $point['date'] <= $windowEndDate,
        ));

        usort(
            $filtered,
            static fn (array $left, array $right): int => $left['date'] <=> $right['date'],
        );

        if (count($filtered) > $maxPoints) {
            $filtered = array_slice($filtered, -$maxPoints);
        }

        return $filtered;
    }

    /**
     * @param  list<array{date: string, rate: float}>  $series
     */
    private function seriesSpanDays(array $series): int
    {
        if ($series === []) {
            return 0;
        }

        if (count($series) === 1) {
            return 1;
        }

        $first = Carbon::parse($series[0]['date'])->startOfDay();
        $last = Carbon::parse($series[array_key_last($series)]['date'])->startOfDay();

        return (int) $first->diffInDays($last) + 1;
    }

    /**
     * @return list<CarbonInterface>
     */
    private function seriesSampleDates(int $days, int $maxPoints): array
    {
        // End on the latest UTC-completable day: CurrencyAPI /v3/historical rejects
        // the incomplete UTC current day (app-local "yesterday" can still be UTC today).
        $end = $this->latestCompletableHistoricalDate();
        $start = $end->copy()->subDays(max(0, $days - 1));
        $spanDays = (int) $start->diffInDays($end) + 1;
        $pointCount = min($spanDays, $maxPoints);

        if ($pointCount === 1) {
            return [$end];
        }

        $datesByKey = [];

        for ($index = 0; $index < $pointCount; $index++) {
            $offsetFromStart = (int) round(($spanDays - 1) * $index / ($pointCount - 1));
            $date = $start->copy()->addDays($offsetFromStart);
            $datesByKey[$date->toDateString()] = $date;
        }

        return array_values($datesByKey);
    }

    /**
     * Latest calendar date CurrencyAPI accepts on /v3/historical (UTC yesterday).
     */
    private function latestCompletableHistoricalDate(): CarbonInterface
    {
        return Carbon::parse(
            $this->latestCompletableHistoricalDateString(),
            (string) config('app.timezone'),
        )->startOfDay();
    }

    private function latestCompletableHistoricalDateString(): string
    {
        return now('UTC')->subDay()->toDateString();
    }

    private function latestCacheKey(string $baseCurrency, string $targetCurrency): string
    {
        $providerName = (string) config('services.currencyapi.provider', CurrencyApiExchangeRateProvider::NAME);

        return implode(':', [
            'currency-rate',
            $providerName,
            strtoupper($baseCurrency),
            strtoupper($targetCurrency),
            'latest',
        ]);
    }

    private function historicalCacheKey(
        string $baseCurrency,
        string $targetCurrency,
        CarbonInterface $date,
    ): string {
        $providerName = (string) config('services.currencyapi.provider', CurrencyApiExchangeRateProvider::NAME);

        return implode(':', [
            'currency-rate',
            $providerName,
            strtoupper($baseCurrency),
            strtoupper($targetCurrency),
            $date->toDateString(),
        ]);
    }

    private function seriesCacheKey(
        string $baseCurrency,
        string $targetCurrency,
        int $days,
        int $maxPoints,
    ): string {
        $providerName = (string) config('services.currencyapi.provider', CurrencyApiExchangeRateProvider::NAME);

        return implode(':', [
            'currency-rate',
            $providerName,
            strtoupper($baseCurrency),
            strtoupper($targetCurrency),
            'series',
            (string) $days,
            (string) $maxPoints,
        ]);
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

    /**
     * @phpstan-assert-if-true list<array{date: string, rate: float|int|string}> $value
     */
    private function isUsableSeries(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach ($value as $point) {
            if (! is_array($point)) {
                return false;
            }

            $rate = $point['rate'] ?? null;

            if (! is_string($point['date'] ?? null)
                || ! is_numeric($rate)
                || ! is_finite((float) $rate)
                || (float) $rate <= 0) {
                return false;
            }
        }

        return true;
    }
}
