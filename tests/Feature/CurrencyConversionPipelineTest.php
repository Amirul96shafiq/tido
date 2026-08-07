<?php

declare(strict_types=1);

use App\Filament\Support\DashboardMonthAnalytics;
use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.currencyapi.api_key' => 'test-key',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
        'services.currencyapi.retry_delays' => [0, 0],
        'cache.default' => 'array',
    ]);
});

test('foreign image receipt is converted to canonical MYR and preserves source metadata', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/usd.jpg', 'fake-image-content');

    Http::preventStrayRequests();
    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'Cursor',
                'invoice_number' => 'K2WQY0IC-0012',
                'date_time' => '2026-07-08 00:00:00',
                'subtotal' => 20.00,
                'total_tax' => 0.00,
                'discount_total' => 14.00,
                'rounding_amount' => 0.00,
                'total_amount' => 6.00,
                'currency' => 'USD',
                'payment_method' => null,
                'items' => [[
                    'description' => 'Cursor Pro',
                    'quantity' => 1,
                    'unit_price' => 20.00,
                    'line_total' => 20.00,
                    'label' => null,
                ]],
            ]),
        ]),
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.5]],
        ]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/usd.jpg',
        'original_filename' => 'usd.jpg',
    ]);

    app()->call([new ExtractReceiptDataJob($invoice->id), 'handle']);

    $invoice->refresh();

    expect($invoice->status)->toBe('parsed')
        ->and($invoice->currency)->toBe('MYR')
        ->and($invoice->original_currency)->toBe('USD')
        ->and($invoice->original_total_amount)->toBe('6.00')
        ->and($invoice->total_amount)->toBe('27.00')
        ->and($invoice->currency_conversion_status)->toBe('converted')
        ->and((float) $invoice->currency_conversion_rate)->toBe(4.5)
        ->and($invoice->currency_conversion_date->format('Y-m-d'))->toBe('2026-07-08')
        ->and($invoice->currency_conversion_provider)->toBe('currencyapi')
        ->and($invoice->invoiceItems->first()->line_total)->toBe('90.00');

    expect(collect(Http::recorded())->filter(
        fn (array $record): bool => str_contains($record[0]->url(), 'currencyapi.test'),
    ))->toHaveCount(1);
});

test('focused document currency detection corrects a MYR misclassification before conversion', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/misclassified-usd.jpg', 'fake-image-content');

    $receipt = [
        'merchant_name' => 'Anysphere, Inc.',
        'invoice_number' => 'USD-332',
        'date_time' => '2026-07-08 00:00:00',
        'subtotal' => 6.00,
        'total_tax' => 0.00,
        'discount_total' => 0.00,
        'rounding_amount' => 0.00,
        'total_amount' => 6.00,
        'currency' => 'MYR',
        'payment_method' => null,
        'items' => [[
            'description' => 'Cursor Pro',
            'quantity' => 1,
            'unit_price' => 6.00,
            'line_total' => 6.00,
            'label' => null,
        ]],
    ];

    Http::preventStrayRequests();
    Http::fake([
        '*/api/generate' => Http::sequence()
            ->push(['response' => json_encode($receipt)])
            ->push(['response' => json_encode([
                'currency' => 'USD',
                'evidence' => 'USD',
            ])]),
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.5]],
        ]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/misclassified-usd.jpg',
        'original_filename' => 'misclassified-usd.jpg',
    ]);

    app()->call([new ExtractReceiptDataJob($invoice->id), 'handle']);

    $invoice->refresh();

    expect($invoice->status)->toBe('parsed')
        ->and($invoice->currency)->toBe('MYR')
        ->and($invoice->original_currency)->toBe('USD')
        ->and($invoice->original_total_amount)->toBe('6.00')
        ->and($invoice->total_amount)->toBe('27.00')
        ->and($invoice->currency_conversion_status)->toBe('converted')
        ->and($invoice->raw_ai_response['currency_detection'])->toBe([
            'currency' => 'USD',
            'source' => 'vision_currency_check',
        ])
        ->and($invoice->invoiceItems->first()->line_total)->toBe('27.00');
});

test('foreign receipt without an available rate stays source-denominated and requires review', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/usd.jpg', 'fake-image-content');

    Http::preventStrayRequests();
    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'Cursor',
                'date_time' => '2026-07-08 00:00:00',
                'subtotal' => 6.00,
                'total_tax' => 0.00,
                'discount_total' => 0.00,
                'rounding_amount' => 0.00,
                'total_amount' => 6.00,
                'currency' => 'USD',
                'items' => [],
            ]),
        ]),
        'https://currencyapi.test/v3/historical*' => Http::failedConnection(),
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/usd.jpg',
        'original_filename' => 'usd.jpg',
    ]);

    app()->call([new ExtractReceiptDataJob($invoice->id), 'handle']);

    $invoice->refresh();

    expect($invoice->status)->toBe('requires_manual_review')
        ->and($invoice->currency)->toBe('USD')
        ->and($invoice->original_currency)->toBe('USD')
        ->and($invoice->original_total_amount)->toBe('6.00')
        ->and($invoice->total_amount)->toBe('6.00')
        ->and($invoice->currency_conversion_status)->toBe('failed')
        ->and($invoice->notes)->toContain('Currency conversion could not be completed');
});

test('legacy invoice 332 style receipts support an explicit source currency override', function () {
    Invoice::unsetEventDispatcher();
    $this->seed(LabelSeeder::class);

    $invoice = Invoice::create([
        'merchant_name' => 'Cursor',
        'invoice_number' => 'K2WQY0IC-0012',
        'receipt_hash' => hash('sha256', 'legacy-332'),
        'date_time' => '2026-07-08 00:00:00',
        'subtotal' => 20.00,
        'total_tax' => 0.00,
        'discount_total' => 14.00,
        'rounding_amount' => 0.00,
        'total_amount' => 6.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'status' => 'requires_manual_review',
        'original_currency' => 'MYR',
        'original_total_amount' => 6.00,
        'currency_conversion_status' => Invoice::CONVERSION_NOT_REQUIRED,
        'raw_ai_response' => [
            'merchant_name' => 'Cursor',
            'invoice_number' => 'K2WQY0IC-0012',
            'date_time' => '2026-07-08 00:00:00',
            'subtotal' => 20.00,
            'total_tax' => 0.00,
            'discount_total' => 14.00,
            'rounding_amount' => 0.00,
            'total_amount' => 6.00,
            'currency' => 'MYR',
            'items' => [[
                'description' => 'Cursor Pro',
                'quantity' => 1,
                'unit_price' => 20.00,
                'line_total' => 20.00,
                'label' => null,
            ]],
        ],
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Cursor Pro',
        'quantity' => 1,
        'unit_price' => 20.00,
        'line_total' => 20.00,
    ]);

    Http::preventStrayRequests();
    $this->artisan('receipts:convert-currency', [
        'invoice' => $invoice->id,
        '--source-currency' => 'USD',
        '--dry-run' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain("Would convert invoice #{$invoice->id}: USD 6");

    expect(Http::recorded())->toHaveCount(0);

    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.5]],
        ]),
    ]);

    $this->artisan('receipts:convert-currency', [
        'invoice' => $invoice->id,
        '--source-currency' => 'USD',
    ])
        ->assertSuccessful();

    $invoice->refresh();

    expect($invoice->currency)->toBe('MYR')
        ->and($invoice->total_amount)->toBe('27.00')
        ->and($invoice->original_currency)->toBe('USD')
        ->and($invoice->currency_conversion_status)->toBe('converted')
        ->and($invoice->invoiceItems->first()->line_total)->toBe('90.00');

    $ollamaRequests = collect(Http::recorded())
        ->filter(fn (array $record): bool => str_contains($record[0]->url(), '/api/generate'));
    expect($ollamaRequests)->toHaveCount(0);
});

test('analytics exclude reviewed foreign rows that were not converted', function () {
    Invoice::unsetEventDispatcher();
    $month = Carbon::create(2026, 8, 1, 0, 0, 0, 'Asia/Kuala_Lumpur');
    $bounds = [
        'start' => $month->copy()->startOfMonth(),
        'end' => $month->copy()->endOfMonth(),
        'previous_start' => $month->copy()->subMonth()->startOfMonth(),
        'previous_end' => $month->copy()->subMonth()->endOfMonth(),
    ];

    Invoice::factory()->create([
        'date_time' => $month->copy()->addDay(),
        'total_amount' => 10.00,
        'subtotal' => 10.00,
        'total_tax' => 0.00,
        'status' => 'reviewed',
        'currency' => 'MYR',
        'currency_conversion_status' => Invoice::CONVERSION_NOT_REQUIRED,
    ]);
    Invoice::factory()->create([
        'date_time' => $month->copy()->addDays(2),
        'total_amount' => 999.00,
        'subtotal' => 999.00,
        'total_tax' => 0.00,
        'status' => 'reviewed',
        'currency' => 'USD',
        'currency_conversion_status' => Invoice::CONVERSION_FAILED,
    ]);
    Invoice::factory()->create([
        'date_time' => $month->copy()->addDays(3),
        'total_amount' => 999.00,
        'subtotal' => 999.00,
        'total_tax' => 0.00,
        'status' => 'pending',
        'currency' => 'USD',
        'currency_conversion_status' => Invoice::CONVERSION_PENDING,
    ]);

    $summary = (new DashboardMonthAnalytics($bounds))->summary();

    expect($summary['current_total'])->toBe(10.0)
        ->and($summary['pending_count'])->toBe(1)
        ->and($summary['processed_count'])->toBe(1);
});
