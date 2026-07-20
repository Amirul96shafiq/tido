<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\PaymentMethodMatcher;
use App\Services\ReceiptParseNormalizer;
use App\Services\ReceiptReparseService;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('extract receipt data job flags mismatched amounts for manual review', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/mock.jpg',
        'original_filename' => 'mock.jpg',
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'TMG',
                'invoice_number' => 'GK02202607140261',
                'date_time' => '14/07/26 20:56:20',
                'subtotal' => 3.6,
                'total_tax' => 0,
                'discount_total' => 0,
                'rounding_amount' => 0,
                'total_amount' => 4.9,
                'currency' => 'MYR',
                'payment_method' => 'debit',
                'items' => [
                    [
                        'description' => 'CHACHO',
                        'quantity' => 1,
                        'unit_price' => 3.6,
                        'line_total' => 3.6,
                        'label' => 'Groceries & Household',
                    ],
                    [
                        'description' => 'BAD LINE',
                        'quantity' => 5,
                        'unit_price' => 2.1,
                        'line_total' => 11.0,
                        'label' => 'Groceries & Household',
                    ],
                ],
            ]),
        ]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    (new ExtractReceiptDataJob($invoice->id))->handle(app(OllamaService::class), app(ReceiptParseNormalizer::class), app(LabelMatcher::class), app(PaymentMethodMatcher::class));

    $invoice->refresh();

    expect($invoice->status)->toBe('requires_manual_review')
        ->and($invoice->paymentMethod->slug)->toBe('other')
        ->and($invoice->date_time->format('Y-m-d'))->toBe('2026-07-14')
        ->and($invoice->invoiceItems)->toHaveCount(2);
});

test('receipt reparse service clears items and dispatches extraction job', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    Invoice::unsetEventDispatcher();

    $invoice = Invoice::factory()->create([
        'status' => 'parsed',
        'image_path' => 'receipts/mock.jpg',
        'merchant_name' => 'Old Merchant',
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Old item',
    ]);

    app(ReceiptReparseService::class)->reparse($invoice);

    $invoice->refresh();

    expect($invoice->status)->toBe('pending')
        ->and($invoice->invoiceItems)->toHaveCount(0);

    Queue::assertPushed(ExtractReceiptDataJob::class, function (ExtractReceiptDataJob $job) use ($invoice): bool {
        return $job->invoiceId === $invoice->id;
    });
});

test('receipts reparse command queues invoice by id', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    Invoice::unsetEventDispatcher();

    $invoice = Invoice::factory()->create([
        'status' => 'parsed',
        'image_path' => 'receipts/mock.jpg',
    ]);

    $this->artisan('receipts:reparse', ['invoice' => $invoice->id])
        ->assertSuccessful();

    $invoice->refresh();

    expect($invoice->status)->toBe('pending');

    Queue::assertPushed(ExtractReceiptDataJob::class);
});

test('extract receipt data job replaces items on successful reparse', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    Invoice::unsetEventDispatcher();

    $invoice = Invoice::factory()->create([
        'status' => 'pending',
        'image_path' => 'receipts/mock.jpg',
        'merchant_name' => 'Pending AI Extraction...',
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Stale item',
        'line_total' => 99.00,
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'KFC',
                'invoice_number' => 'INV-100',
                'date_time' => '2026-06-27 12:00:00',
                'subtotal' => 20.00,
                'total_tax' => 1.20,
                'discount_total' => 0,
                'rounding_amount' => 0,
                'total_amount' => 21.20,
                'currency' => 'MYR',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'description' => '2-pc Chicken Meal',
                        'quantity' => 1,
                        'unit_price' => 20.00,
                        'line_total' => 20.00,
                        'label' => 'Food & Dining',
                    ],
                ],
            ]),
        ]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    (new ExtractReceiptDataJob($invoice->id))->handle(app(OllamaService::class), app(ReceiptParseNormalizer::class), app(LabelMatcher::class), app(PaymentMethodMatcher::class));

    $invoice->refresh();

    expect($invoice->status)->toBe('parsed')
        ->and($invoice->merchant_name)->toBe('KFC')
        ->and($invoice->invoiceItems)->toHaveCount(1)
        ->and($invoice->invoiceItems->first()->description)->toBe('2-pc Chicken Meal');
});

test('extract receipt data job flags implausible date for manual review', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    $uploadTime = now();

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => $uploadTime,
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/mock.jpg',
        'original_filename' => 'mock.jpg',
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'PETRON',
                'invoice_number' => '9H51204',
                'date_time' => '2018-07-13T16:32:22Z',
                'subtotal' => 8,
                'total_tax' => 0,
                'discount_total' => 0,
                'rounding_amount' => 0,
                'total_amount' => 8,
                'currency' => 'MYR',
                'payment_method' => 'mastercard',
                'items' => [
                    [
                        'description' => 'Fuel',
                        'quantity' => 1,
                        'unit_price' => 8,
                        'line_total' => 8,
                        'label' => 'Transportation & Fuel',
                    ],
                ],
            ]),
        ]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    (new ExtractReceiptDataJob($invoice->id))->handle(app(OllamaService::class), app(ReceiptParseNormalizer::class), app(LabelMatcher::class), app(PaymentMethodMatcher::class));

    $invoice->refresh();

    expect($invoice->status)->toBe('requires_manual_review')
        ->and($invoice->date_time->format('Y-m-d'))->toBe('2018-07-13')
        ->and($invoice->notes)->toContain('[AI] Receipt date/time looks implausible and needs review.');
});

test('extract receipt data job keeps upload date when ai datetime cannot be parsed', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    $uploadTime = now()->startOfSecond();

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => $uploadTime,
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/mock.jpg',
        'original_filename' => 'mock.jpg',
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'PIZZARO',
                'invoice_number' => '#1-00003882',
                'date_time' => 'garbage-date',
                'subtotal' => 74.8,
                'total_tax' => 0,
                'discount_total' => 0,
                'rounding_amount' => 0,
                'total_amount' => 74.8,
                'currency' => 'MYR',
                'payment_method' => null,
                'items' => [
                    [
                        'description' => 'Pizza',
                        'quantity' => 1,
                        'unit_price' => 74.8,
                        'line_total' => 74.8,
                        'label' => 'Food & Dining',
                    ],
                ],
            ]),
        ]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    (new ExtractReceiptDataJob($invoice->id))->handle(app(OllamaService::class), app(ReceiptParseNormalizer::class), app(LabelMatcher::class), app(PaymentMethodMatcher::class));

    $invoice->refresh();

    expect($invoice->status)->toBe('requires_manual_review')
        ->and($invoice->date_time->equalTo($uploadTime))->toBeTrue()
        ->and($invoice->notes)->toContain('[AI] Receipt date/time could not be parsed.');
});

test('extract receipt data job parses day first datetime with T suffix correctly', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/mock.jpg',
        'original_filename' => 'mock.jpg',
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'BIG SUPERMARKET SDN BHD',
                'invoice_number' => 'SGR0248021067105',
                'date_time' => '11/07/26T17:20',
                'subtotal' => 4.00,
                'total_tax' => 0,
                'discount_total' => 0,
                'rounding_amount' => 0,
                'total_amount' => 4.00,
                'currency' => 'MYR',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'description' => 'DAUN KETUMBAR',
                        'quantity' => 1,
                        'unit_price' => 4.00,
                        'line_total' => 4.00,
                        'label' => 'Groceries & Household',
                    ],
                ],
            ]),
        ]),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    (new ExtractReceiptDataJob($invoice->id))->handle(app(OllamaService::class), app(ReceiptParseNormalizer::class), app(LabelMatcher::class), app(PaymentMethodMatcher::class));

    $invoice->refresh();

    expect($invoice->status)->toBe('parsed')
        ->and($invoice->date_time->format('Y-m-d H:i'))->toBe('2026-07-11 17:20')
        ->and($invoice->notes)->toBeNull();
});
