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
        ->assertSee('1 USD = RM 4.5123')
        ->assertSee('08 Jul 2026 • currencyapi')
        ->assertDontSee('1 USD as of 08 Jul 2026 via currencyapi')
        ->assertSee('0.0000 (0.00%) 30D')
        ->assertSee('Low')
        ->assertSee('High')
        ->assertSee('Avg')
        ->assertSee('USD')
        ->assertSee('MYR')
        ->assertSee("usd: '1'", false)
        ->assertSee('4.5123')
        ->assertSee('sm:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]', false)
        ->assertSee('fi-wi-currency-rate-sparkline', false)
        ->assertSee('statsOverviewStatChart', false)
        ->assertSee('Swap currencies');

    $html = Livewire::test(CurrentCurrency::class)->html();

    expect(substr_count($html, 'fi-wi-current-currency-surface'))->toBeGreaterThanOrEqual(3);

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
        ->assertDontSee('Swap currencies')
        ->assertDontSee('fi-wi-currency-rate-sparkline');
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
        ->assertSee('1 USD = RM 4.0910')
        ->assertSee('07 Aug 2026 • currencyapi')
        ->assertDontSee('1 USD as of 07 Aug 2026 via currencyapi')
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
        ->assertSee('1 USD = RM 4.2500')
        ->assertSee('08 Aug 2026 • currencyapi')
        ->assertSee('Rate history unavailable')
        ->assertDontSee('fi-wi-currency-rate-sparkline')
        ->assertDontSee('statsOverviewStatChart')
        ->assertDontSee('Low')
        ->assertDontSee('30D');
});

test('currency widget shows a 30-day change and range from series history', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'Asia/Kuala_Lumpur'));

    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/latest*' => Http::response([
            'meta' => ['last_updated_at' => '2026-08-08T10:15:00Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.30]],
        ]),
        'https://currencyapi.test/v3/historical*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $date = (string) ($query['date'] ?? '');
            $start = Carbon::parse('2026-07-10', 'Asia/Kuala_Lumpur')->startOfDay();
            $offset = max(0, (int) $start->diffInDays(Carbon::parse($date, 'Asia/Kuala_Lumpur')->startOfDay()));
            $value = 4.00 + ($offset * 0.01);

            return Http::response([
                'meta' => ['last_updated_at' => $date.'T23:59:59Z'],
                'data' => ['MYR' => ['code' => 'MYR', 'value' => $value]],
            ]);
        },
    ]);

    Livewire::test(CurrentCurrency::class)
        ->assertSuccessful()
        ->assertSee('1 USD = RM 4.3000')
        ->assertSee('30D')
        ->assertSee('Low')
        ->assertSee('High')
        ->assertSee('Avg')
        ->assertSee('text-success-600', false);
});
