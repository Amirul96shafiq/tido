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
        'Subtotal USD 20.00 Total USD 6.00 Charged RM25.44 using 1 USD = 4.2397 MYR',
        ['page-image'],
        'MYR',
    );

    expect($result)->toBe([
        'currency' => 'USD',
        'source' => 'document_text',
        'rate' => 4.2397,
        'rate_source' => 'printed_receipt_rate',
    ])
        ->and(Http::recorded())->toHaveCount(0);
});

test('treats an unqualified dollar amount as USD when no competing marker exists', function () {
    $detector = app(ReceiptCurrencyDetector::class);

    expect($detector->detectFromText('Total $6.00'))->toBe('USD')
        ->and($detector->detectFromText('Total SGD $6.00'))->toBe('SGD')
        ->and($detector->detectFromText('Total USD 6.00 and MYR 27.00'))->toBeNull()
        ->and($detector->detectFromText('Total $6.00 Charged RM25.44 using 1 USD = 4.2397 MYR'))->toBe('USD');
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
            && str_contains((string) $request['prompt'], 'source currency')
            && str_contains((string) $request['prompt'], 'explicitly prints a source-currency-to-MYR rate')
            && $request['images'] === ['page-image'];
    });
});

test('uses an explicitly printed vision rate when PDF text is unavailable', function () {
    Http::fake([
        'http://ollama.test/api/generate' => Http::response([
            'response' => json_encode([
                'currency' => 'USD',
                'evidence' => 'USD',
                'rate' => 4.2397,
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
        'rate' => 4.2397,
        'rate_source' => 'printed_receipt_rate',
    ]);
});

test('runs a dedicated conversion-note pass when the currency pass omits the printed rate', function () {
    Http::fake([
        'http://ollama.test/api/generate' => Http::sequence()
            ->push(['response' => json_encode([
                'currency' => 'USD',
                'evidence' => 'USD',
            ])])
            ->push(['response' => json_encode([
                'currency' => 'USD',
                'rate' => 4.2397,
                'evidence' => 'Charged RM25.44 using 1 USD = 4.2397 MYR',
            ])]),
    ]);

    $result = app(ReceiptCurrencyDetector::class)->detect(
        null,
        ['page-image'],
        'MYR',
    );

    expect($result)->toBe([
        'currency' => 'USD',
        'source' => 'vision_currency_check',
        'rate' => 4.2397,
        'rate_source' => 'printed_receipt_rate',
    ]);

    $requests = collect(Http::recorded())
        ->map(fn (array $record): string => (string) $record[0]['prompt'])
        ->values();

    expect($requests)->toHaveCount(2)
        ->and($requests[1])->toContain('printed currency-conversion statement')
        ->and($requests[1])->toContain('Never calculate or infer the rate');
});

test('keeps a foreign extraction currency when vision sees only a settlement MYR marker', function () {
    Http::fake([
        'http://ollama.test/api/generate' => Http::response([
            'response' => json_encode([
                'currency' => 'MYR',
                'evidence' => 'RM25.44 using 1 USD = 4.2397 MYR',
            ]),
        ]),
    ]);

    $result = app(ReceiptCurrencyDetector::class)->detect(
        null,
        ['page-image'],
        'USD',
    );

    expect($result)->toBe([
        'currency' => 'USD',
        'source' => 'receipt_extraction_fallback',
        'rate' => 4.2397,
        'rate_source' => 'printed_receipt_rate',
    ]);
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
        null,
    );

    expect($result)->toBe([
        'currency' => null,
        'source' => 'conflicting_vision_evidence',
    ]);
});
