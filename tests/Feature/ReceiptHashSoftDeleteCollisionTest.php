<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\SendWhatsAppDocumentParsedJob;
use App\Models\Invoice;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\ReceiptParseNormalizer;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('extract receipt data job uniquifies hash when soft-deleted invoice owns the same hash', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_NEW.jpg', 'fake-image-content');
    $this->seed(LabelSeeder::class);

    $invoiceNumber = '17086';
    $dateTime = '2026-07-18 22:13:19';
    $total = '7.80';
    $baseHash = hash('sha256', $invoiceNumber.$dateTime.$total);

    $trashed = Invoice::factory()->create([
        'invoice_number' => $invoiceNumber,
        'date_time' => $dateTime,
        'total_amount' => $total,
        'receipt_hash' => $baseHash,
        'status' => 'parsed',
        'source' => 'whatsapp',
    ]);
    $trashed->delete();

    expect(Invoice::withTrashed()->where('receipt_hash', $baseHash)->exists())->toBeTrue();

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'RESTORAN NASI KANDAR RASSEN',
                'invoice_number' => $invoiceNumber,
                'date_time' => $dateTime,
                'subtotal' => 7.80,
                'total_tax' => 0.00,
                'discount_total' => 0.00,
                'rounding_amount' => 0.00,
                'total_amount' => 7.80,
                'currency' => 'MYR',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'description' => 'Roti Telur',
                        'quantity' => 1,
                        'unit_price' => 2.80,
                        'line_total' => 2.80,
                        'label' => 'Food & Dining',
                    ],
                    [
                        'description' => 'Ayam Goreng',
                        'quantity' => 1,
                        'unit_price' => 5.00,
                        'line_total' => 5.00,
                        'label' => 'Food & Dining',
                    ],
                ],
            ]),
        ]),
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
        'image_path' => 'receipts/wa_NEW.jpg',
        'original_filename' => 'wa_NEW.jpg',
    ]);

    (new ExtractReceiptDataJob($invoice->id))->handle(
        new OllamaService,
        new ReceiptParseNormalizer,
        new LabelMatcher,
    );

    $invoice->refresh();

    expect($invoice->status)->toBeIn(['parsed', 'requires_manual_review'])
        ->and($invoice->receipt_hash)->not->toBe($baseHash)
        ->and($invoice->receipt_hash)->toBe(hash('sha256', $baseHash.'|'.$invoice->id));

    Queue::assertPushed(SendWhatsAppDocumentParsedJob::class, function (SendWhatsAppDocumentParsedJob $job) use ($invoice): bool {
        return $job->invoiceId === $invoice->id;
    });
});
