<?php

declare(strict_types=1);

use App\Services\Currency\CurrencyApiExchangeRateProvider;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'services.currencyapi.provider' => 'currencyapi',
        'services.currencyapi.api_key' => 'test-key',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
        'services.currencyapi.retry_delays' => [0, 0],
    ]);
});

test('currency api provider requests a historical rate with source and target currencies', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.512345]],
        ]),
    ]);

    $provider = new CurrencyApiExchangeRateProvider;
    $rate = $provider->rate('USD', 'MYR', Carbon::parse('2026-07-08', 'Asia/Kuala_Lumpur'));

    expect($rate['rate'])->toBe(4.512345)
        ->and($rate['effective_date'])->toBe('2026-07-08')
        ->and($rate['provider'])->toBe('currencyapi');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'date=2026-07-08')
            && str_contains($request->url(), 'base_currency=USD')
            && str_contains($request->url(), 'currencies=MYR')
            && $request->hasHeader('apikey', 'test-key');
    });
});

test('exchange rate service caches the same source date lookup', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.5]],
        ]),
    ]);

    $service = new ExchangeRateService(new CurrencyApiExchangeRateProvider);
    $date = Carbon::parse('2026-07-08', 'Asia/Kuala_Lumpur');

    $first = $service->rate('USD', 'MYR', $date);
    $second = $service->rate('USD', 'MYR', $date);

    expect($first)->toBe($second);
    Http::assertSentCount(1);
});

test('currency conversion converts every money field with one rate', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.5]],
        ]),
    ]);

    $service = new CurrencyConversionService(
        new ExchangeRateService(new CurrencyApiExchangeRateProvider),
    );
    $normalized = [
        'merchant_name' => 'Cursor',
        'invoice_number' => 'K2WQY0IC-0012',
        'date_time' => Carbon::parse('2026-07-08', 'Asia/Kuala_Lumpur'),
        'subtotal' => 20.0,
        'total_tax' => 0.0,
        'discount_total' => 14.0,
        'rounding_amount' => 0.0,
        'total_amount' => 6.0,
        'currency' => 'USD',
        'payment_method' => null,
        'items' => [[
            'description' => 'Cursor Pro',
            'quantity' => 1.0,
            'unit_price' => 20.0,
            'line_total' => 20.0,
            'serial_number' => null,
            'label' => null,
        ]],
    ];

    $result = $service->convert($normalized, $normalized['date_time']);

    expect($result['normalized']['currency'])->toBe('MYR')
        ->and($result['normalized']['subtotal'])->toBe(90.0)
        ->and($result['normalized']['discount_total'])->toBe(63.0)
        ->and($result['normalized']['total_amount'])->toBe(27.0)
        ->and($result['normalized']['items'][0]['unit_price'])->toBe(90.0)
        ->and($result['normalized']['items'][0]['line_total'])->toBe(90.0)
        ->and($result['metadata']['original_currency'])->toBe('USD')
        ->and($result['metadata']['original_total_amount'])->toBe(6.0)
        ->and($result['metadata']['currency_conversion_status'])->toBe('converted')
        ->and($result['metadata']['currency_conversion_rate'])->toBe(4.5);
});

test('MYR conversion skips the provider', function () {
    Http::preventStrayRequests();

    $service = new CurrencyConversionService(
        new ExchangeRateService(new CurrencyApiExchangeRateProvider),
    );
    $normalized = [
        'subtotal' => 10.0,
        'total_tax' => 0.0,
        'discount_total' => 0.0,
        'rounding_amount' => 0.0,
        'total_amount' => 10.0,
        'currency' => 'MYR',
        'items' => [],
    ];

    $result = $service->convert($normalized, null);

    expect($result['normalized'])->toBe($normalized)
        ->and($result['metadata']['currency_conversion_status'])->toBe('not_required')
        ->and($result['metadata']['currency_conversion_rate'])->toBeNull();
});
