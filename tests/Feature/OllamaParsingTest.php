<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\PaymentMethodMatcher;
use App\Services\ReceiptParseNormalizer;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('ollama service clean and decode json parses clean and fenced markdown', function () {
    $service = new OllamaService;

    $json = '{"merchant_name": "McDonalds", "total_amount": 10.60}';
    expect($service->cleanAndDecodeJson($json))->toBe([
        'merchant_name' => 'McDonalds',
        'total_amount' => 10.60,
    ]);

    $fenced = "```json\n".$json."\n```";
    expect($service->cleanAndDecodeJson($fenced))->toBe([
        'merchant_name' => 'McDonalds',
        'total_amount' => 10.60,
    ]);
});

test('extract receipt data job processes mock response and updates status', function () {
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

    Queue::assertPushed(ExtractReceiptDataJob::class);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'KFC',
                'invoice_number' => 'INV-999',
                'date_time' => '2026-06-27 12:00:00',
                'subtotal' => 20.00,
                'total_tax' => 1.20,
                'discount_total' => 0.50,
                'rounding_amount' => -0.01,
                'total_amount' => 20.69,
                'currency' => 'MYR',
                'payment_method' => 'mastercard',
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

    $job = new ExtractReceiptDataJob($invoice->id);
    $job->handle(new OllamaService, new ReceiptParseNormalizer, new LabelMatcher, new PaymentMethodMatcher);

    $invoice->refresh();

    expect($invoice->status)->toBe('parsed');
    expect($invoice->merchant_name)->toBe('KFC');
    expect($invoice->invoice_number)->toBe('INV-999');
    expect($invoice->total_amount)->toBe('20.69');
    expect($invoice->discount_total)->toBe('0.50');
    expect($invoice->rounding_amount)->toBe('-0.01');
    expect($invoice->paymentMethod->slug)->toBe('mastercard');
    expect($invoice->invoiceItems)->toHaveCount(1);
    expect($invoice->invoiceItems->first()->description)->toBe('2-pc Chicken Meal');
    expect($invoice->invoiceItems->first()->label->name)->toBe('Food & Dining');
});
