<?php

declare(strict_types=1);

use App\Filament\Widgets\CurrentCurrency;
use App\Services\Currency\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'services.currencyapi.provider' => 'currencyapi',
        'services.currencyapi.api_key' => 'test-key',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
        'services.currencyapi.cainfo' => null,
        'services.currencyapi.retry_delays' => [0, 0],
        'services.currencyapi.series_max_points' => 7,
        'services.currencyapi.series_cache_ttl' => 604800,
    ]);
    Cache::flush();
});

test('currency refresh rates command primes latest and series caches', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'Asia/Kuala_Lumpur'));
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/latest*' => Http::response([
            'meta' => ['last_updated_at' => '2026-08-08T10:15:00Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.25]],
        ]),
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-08-07T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.20]],
        ]),
    ]);

    $exitCode = Artisan::call('currency:refresh-rates', [
        '--base' => 'USD',
        '--target' => 'MYR',
        '--days' => CurrentCurrency::SERIES_DAYS,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Refreshed USD→MYR');

    $service = app(ExchangeRateService::class);

    expect($service->cachedLatest('USD', 'MYR')['rate'])->toBe(4.25)
        ->and($service->cachedSeries('USD', 'MYR', CurrentCurrency::SERIES_DAYS))->not->toBeEmpty();

    Http::assertSentCount(1 + 7);

    Carbon::setTestNow();
});

test('currency refresh rates reuses sparkline last-good on same-day re-run', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'Asia/Kuala_Lumpur'));
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/latest*' => Http::response([
            'meta' => ['last_updated_at' => '2026-08-08T10:15:00Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.25]],
        ]),
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-08-07T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.20]],
        ]),
    ]);

    Artisan::call('currency:refresh-rates', [
        '--base' => 'USD',
        '--target' => 'MYR',
        '--days' => CurrentCurrency::SERIES_DAYS,
    ]);

    $sentAfterCold = count(Http::recorded());

    expect($sentAfterCold)->toBe(1 + 7);

    $exitCode = Artisan::call('currency:refresh-rates', [
        '--base' => 'USD',
        '--target' => 'MYR',
        '--days' => CurrentCurrency::SERIES_DAYS,
    ]);

    expect($exitCode)->toBe(0)
        ->and(count(Http::recorded()) - $sentAfterCold)->toBe(1)
        ->and(app(ExchangeRateService::class)->cachedSeries('USD', 'MYR', CurrentCurrency::SERIES_DAYS))
        ->toHaveCount(7);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v3/latest'));

    Carbon::setTestNow();
});

test('currency refresh rates is scheduled daily at midnight in the app timezone', function () {
    $event = collect(Schedule::events())
        ->first(fn ($scheduled): bool => str_contains($scheduled->command ?? '', 'currency:refresh-rates'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->timezone)->toBe(config('app.timezone'));
});
