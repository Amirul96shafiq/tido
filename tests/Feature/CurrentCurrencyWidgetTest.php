<?php

declare(strict_types=1);

use App\Filament\Widgets\CurrentCurrency;
use App\Services\Currency\ExchangeRateService;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'services.currencyapi.provider' => 'currencyapi',
        'services.currencyapi.api_key' => 'test-key',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
        'services.currencyapi.cainfo' => null,
        'services.currencyapi.retry_delays' => [0, 0],
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array<string, Factory|PromiseInterface|callable>
 */
function fakeCurrencyWidgetHttp(float $latestRate = 4.512345, string $latestUpdatedAt = '2026-07-08T23:59:59Z'): array
{
    return [
        'https://currencyapi.test/v3/latest*' => Http::response([
            'meta' => ['last_updated_at' => $latestUpdatedAt],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => $latestRate]],
        ]),
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => $latestRate]],
        ]),
    ];
}

test('currency widget renders the current usd to myr rate with provider context', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Asia/Kuala_Lumpur'));

    Http::preventStrayRequests();
    Http::fake(fakeCurrencyWidgetHttp());

    Livewire::test(CurrentCurrency::class)
        ->assertSuccessful()
        ->assertSee('USD to MYR')
        ->assertSee('RM 4.5123')
        ->assertSee('1 USD as of 08 Jul 2026 via currencyapi')
        ->assertSee('USD')
        ->assertSee('MYR')
        ->assertSee("usd: '1'", false)
        ->assertSee('4.5123')
        ->assertSee('30-day trend')
        ->assertSee('aria-label="USD to MYR exchange rate over the last 30 days"', false)
        ->assertSee('Swap currencies');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v3/latest?')
            && str_contains($request->url(), 'base_currency=USD')
            && str_contains($request->url(), 'currencies=MYR')
            && $request->hasHeader('apikey', 'test-key');
    });
});

test('currency widget renders an unavailable state when the provider is not configured', function () {
    Http::preventStrayRequests();
    config(['services.currencyapi.api_key' => null]);

    Livewire::test(CurrentCurrency::class)
        ->assertSuccessful()
        ->assertSee('Unavailable')
        ->assertSee('Current exchange rate unavailable')
        ->assertDontSee('30-day trend')
        ->assertDontSee('Swap currencies');
});

test('currency widget uses half-width desktop layout', function () {
    Http::preventStrayRequests();
    Http::fake(fakeCurrencyWidgetHttp());

    $widget = Livewire::test(CurrentCurrency::class)->instance();

    expect($widget->getColumnSpan())->toBe([
        'default' => 'full',
        'xl' => 6,
    ]);
});

test('currency widget shows last good rate when the live provider is unreachable', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'Asia/Kuala_Lumpur'));

    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/latest*' => Http::sequence()
            ->push([
                'meta' => ['last_updated_at' => '2026-08-07T23:59:59Z'],
                'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.091]],
            ])
            ->pushFailedConnection('simulated outage'),
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-08-07T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.091]],
        ]),
    ]);

    app(ExchangeRateService::class)->latest('USD', 'MYR');
    Cache::forget('currency-rate:currencyapi:USD:MYR:latest');

    Livewire::test(CurrentCurrency::class)
        ->assertSuccessful()
        ->assertSee('USD to MYR')
        ->assertSee('RM 4.0910')
        ->assertSee('1 USD as of 07 Aug 2026 via currencyapi')
        ->assertDontSee('Unavailable');
});

test('currency widget shows rate history unavailable when the series cannot be loaded', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'Asia/Kuala_Lumpur'));

    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/latest*' => Http::response([
            'meta' => ['last_updated_at' => '2026-08-08T10:15:00Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.25]],
        ]),
        'https://currencyapi.test/v3/historical*' => Http::response([], 500),
    ]);

    Livewire::test(CurrentCurrency::class)
        ->assertSuccessful()
        ->assertSee('RM 4.2500')
        ->assertSee('30-day trend')
        ->assertSee('Rate history unavailable')
        ->assertDontSee('aria-label="USD to MYR exchange rate over the last 30 days"', false);
});
