<?php

declare(strict_types=1);

use App\Support\ProductionEnvironmentBaseline;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function withProductionEnvironmentBaseline(array $overrides, callable $callback): void
{
    $originalEnvironment = app()->environment();

    app()->detectEnvironment(fn (): string => 'production');

    $originalConfig = [
        'app.debug' => config('app.debug'),
        'session.secure' => config('session.secure'),
        'session.http_only' => config('session.http_only'),
        'session.same_site' => config('session.same_site'),
        'session.lifetime' => config('session.lifetime'),
        'session.encrypt' => config('session.encrypt'),
    ];

    config(array_merge([
        'app.debug' => false,
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
    ], $overrides));

    try {
        $callback();
    } finally {
        config($originalConfig);
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }
}

test('production environment baseline is a no-op outside production', function (): void {
    config([
        'app.debug' => true,
        'session.secure' => false,
        'session.http_only' => false,
        'session.same_site' => 'none',
    ]);

    ProductionEnvironmentBaseline::assert();

    expect(true)->toBeTrue();
});

test('production environment baseline passes with secure session defaults', function (): void {
    withProductionEnvironmentBaseline([], function (): void {
        ProductionEnvironmentBaseline::assert();

        expect(true)->toBeTrue();
    });
});

test('production environment baseline accepts reviewed household session choices', function (): void {
    withProductionEnvironmentBaseline([
        'session.lifetime' => 10080,
        'session.encrypt' => false,
    ], function (): void {
        ProductionEnvironmentBaseline::assert();

        expect(true)->toBeTrue();
    });
});

test('production environment baseline rejects debug mode', function (): void {
    withProductionEnvironmentBaseline(['app.debug' => true], function (): void {
        ProductionEnvironmentBaseline::assert();
    });
})->throws(RuntimeException::class, ProductionEnvironmentBaseline::UNAVAILABLE_MESSAGE);

test('production environment baseline rejects insecure session cookies', function (): void {
    withProductionEnvironmentBaseline(['session.secure' => false], function (): void {
        ProductionEnvironmentBaseline::assert();
    });
})->throws(RuntimeException::class, ProductionEnvironmentBaseline::UNAVAILABLE_MESSAGE);

test('production environment baseline rejects non-http-only session cookies', function (): void {
    withProductionEnvironmentBaseline(['session.http_only' => false], function (): void {
        ProductionEnvironmentBaseline::assert();
    });
})->throws(RuntimeException::class, ProductionEnvironmentBaseline::UNAVAILABLE_MESSAGE);

test('production environment baseline rejects same-site none', function (): void {
    withProductionEnvironmentBaseline(['session.same_site' => 'none'], function (): void {
        ProductionEnvironmentBaseline::assert();
    });
})->throws(RuntimeException::class, ProductionEnvironmentBaseline::UNAVAILABLE_MESSAGE);

test('production environment baseline rejects null same-site', function (): void {
    withProductionEnvironmentBaseline(['session.same_site' => null], function (): void {
        ProductionEnvironmentBaseline::assert();
    });
})->throws(RuntimeException::class, ProductionEnvironmentBaseline::UNAVAILABLE_MESSAGE);
