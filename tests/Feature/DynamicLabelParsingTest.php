<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use App\Models\Label;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\ReceiptParseNormalizer;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('extract receipt data job maps custom user label from ai response', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/mock.jpg', 'fake-image-content');

    $this->seed(LabelSeeder::class);

    Label::factory()->create([
        'name' => 'Pet Supplies',
        'slug' => 'pet-supplies',
        'description' => 'Pet food, grooming, vet supplies',
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
        'image_path' => 'receipts/mock.jpg',
        'original_filename' => 'mock.jpg',
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'Pet Mart',
                'invoice_number' => 'PM-001',
                'date_time' => '2026-07-15 10:00:00',
                'subtotal' => 25.00,
                'total_tax' => 0.00,
                'discount_total' => 0.00,
                'rounding_amount' => 0.00,
                'total_amount' => 25.00,
                'currency' => 'MYR',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'description' => 'Cat kibble 2kg',
                        'quantity' => 1,
                        'unit_price' => 25.00,
                        'line_total' => 25.00,
                        'label' => 'Pet Supplies',
                    ],
                ],
            ]),
        ]),
    ]);

    $job = new ExtractReceiptDataJob($invoice->id);
    $job->handle(new OllamaService, new ReceiptParseNormalizer, new LabelMatcher);

    $invoice->refresh();

    expect($invoice->invoiceItems)->toHaveCount(1)
        ->and($invoice->invoiceItems->first()->label->name)->toBe('Pet Supplies');
});
