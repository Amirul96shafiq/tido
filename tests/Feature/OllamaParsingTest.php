<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Expense;
use App\Services\OllamaService;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

test('ollama vision requests reserve enough context for long receipt responses', function () {
    config(['services.ollama.num_ctx' => 8192]);

    Http::fake([
        '*/api/generate' => Http::response(['response' => '{}']),
    ]);

    (new OllamaService)->parseReceipt('base64-image', 'receipt prompt');

    Http::assertSent(function (Request $request): bool {
        return $request->data()['options']['num_ctx'] === 8192;
    });
});

test('extract receipt data job processes mock response and updates status', function () {
    Queue::fake();

    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    $expense = Expense::create([
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

    $job = new ExtractReceiptDataJob($expense->id);
    app()->call([$job, 'handle']);

    $expense->refresh();

    expect($expense->status)->toBe('parsed');
    expect($expense->merchant_name)->toBe('KFC');
    expect($expense->invoice_number)->toBe('INV-999');
    expect($expense->total_amount)->toBe('20.69');
    expect($expense->discount_total)->toBe('0.50');
    expect($expense->rounding_amount)->toBe('-0.01');
    expect($expense->paymentMethod->slug)->toBe('mastercard');
    expect($expense->expenseItems)->toHaveCount(1);
    expect($expense->expenseItems->first()->description)->toBe('2-pc Chicken Meal');
    expect($expense->expenseItems->first()->label->name)->toBe('Food & Dining');
});
