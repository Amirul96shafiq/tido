<?php

declare(strict_types=1);

use App\Filament\Widgets\CurrentCurrency;
use Carbon\Carbon;
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

test('currency widget renders the current usd to myr rate with provider context', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Asia/Kuala_Lumpur'));

    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/latest*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.512345]],
        ]),
    ]);

    Livewire::test(CurrentCurrency::class)
        ->assertSuccessful()
        ->assertSee('USD to MYR')
        ->assertSee('RM 4.5123')
        ->assertSee('1 USD as of 08 Jul 2026 via currencyapi');

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
        ->assertSee('Current exchange rate unavailable');
});

test('currency widget uses half-width desktop and one internal column', function () {
    $widget = Livewire::test(CurrentCurrency::class)->instance();
    $columns = (new ReflectionProperty($widget, 'columns'))->getValue($widget);

    expect($widget->getColumnSpan())->toBe([
        'default' => 'full',
        'xl' => 6,
    ])->and($columns)->toBe(1);
});
