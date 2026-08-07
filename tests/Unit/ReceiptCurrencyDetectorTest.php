<?php

declare(strict_types=1);

use App\Services\Currency\ReceiptCurrencyDetector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.ollama.timeout' => 10,
    ]);

    Http::preventStrayRequests();
});

test('detects explicit currency evidence from PDF text without another vision request', function () {
    $result = app(ReceiptCurrencyDetector::class)->detect(
        'Subtotal USD 20.00 Total USD 6.00',
        ['page-image'],
        'MYR',
    );

    expect($result)->toBe([
        'currency' => 'USD',
        'source' => 'document_text',
    ])
        ->and(Http::recorded())->toHaveCount(0);
});

test('treats an unqualified dollar amount as USD when no competing marker exists', function () {
    $detector = app(ReceiptCurrencyDetector::class);

    expect($detector->detectFromText('Total $6.00'))->toBe('USD')
        ->and($detector->detectFromText('Total SGD $6.00'))->toBe('SGD')
        ->and($detector->detectFromText('Total USD 6.00 and MYR 27.00'))->toBeNull();
});

test('uses a focused vision currency check when PDF text has no currency evidence', function () {
    Http::fake([
        'http://ollama.test/api/generate' => Http::response([
            'response' => json_encode([
                'currency' => 'USD',
                'evidence' => 'USD',
            ]),
        ]),
    ]);

    $result = app(ReceiptCurrencyDetector::class)->detect(
        null,
        ['page-image'],
        'MYR',
    );

    expect($result)->toBe([
        'currency' => 'USD',
        'source' => 'vision_currency_check',
    ]);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://ollama.test/api/generate'
            && str_contains((string) $request['prompt'], 'Never assume MYR')
            && $request['images'] === ['page-image'];
    });
});

test('does not fall back to MYR when focused currency detection is unavailable', function () {
    Http::fake([
        'http://ollama.test/api/generate' => Http::response(['response' => '{}']),
    ]);

    $result = app(ReceiptCurrencyDetector::class)->detect(
        null,
        ['page-image'],
        'MYR',
    );

    expect($result)->toBe([
        'currency' => null,
        'source' => 'undetermined',
    ]);
});

test('rejects conflicting currency evidence across PDF pages', function () {
    Http::fake([
        'http://ollama.test/api/generate' => Http::sequence()
            ->push(['response' => json_encode(['currency' => 'MYR', 'evidence' => 'RM'])])
            ->push(['response' => json_encode(['currency' => 'USD', 'evidence' => 'USD'])]),
    ]);

    $result = app(ReceiptCurrencyDetector::class)->detect(
        null,
        ['page-one', 'page-two'],
        'USD',
    );

    expect($result)->toBe([
        'currency' => null,
        'source' => 'conflicting_vision_evidence',
    ]);
});
