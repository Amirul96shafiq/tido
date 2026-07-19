<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\SendWhatsAppDocumentParsedJob;
use App\Models\Invoice;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\ReceiptParseNormalizer;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppPublicUrl;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function fakeSuccessfulOllamaResponse(): void
{
    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => '7-Eleven',
                'invoice_number' => 'INV-WA-1',
                'date_time' => '2026-07-18 14:00:00',
                'subtotal' => 2.00,
                'total_tax' => 0.00,
                'discount_total' => 0.00,
                'rounding_amount' => 0.00,
                'total_amount' => 2.00,
                'currency' => 'MYR',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'description' => 'Item',
                        'quantity' => 1,
                        'unit_price' => 2.00,
                        'line_total' => 2.00,
                        'label' => 'Food & Dining',
                    ],
                ],
            ]),
        ]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);
}

test('extract receipt data job dispatches gated document parsed whatsapp job', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_MSG123.jpg', 'fake-image-content');

    config([
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
    ]);

    fakeSuccessfulOllamaResponse();
    $this->seed(LabelSeeder::class);

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
        'image_path' => 'receipts/wa_MSG123.jpg',
        'original_filename' => 'wa_MSG123.jpg',
    ]);

    $job = new ExtractReceiptDataJob($invoice->id);
    $job->handle(
        new OllamaService,
        new ReceiptParseNormalizer,
        new LabelMatcher,
    );

    expect($invoice->fresh()->status)->toBe('parsed');

    Queue::assertPushed(SendWhatsAppDocumentParsedJob::class, function (SendWhatsAppDocumentParsedJob $job) use ($invoice): bool {
        return $job->invoiceId === $invoice->id;
    });

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
});

test('document parsed job waits while document received ack is pending then sends text url links', function () {
    Storage::fake('local');
    Storage::put('receipts/wa_MSG123.jpg', 'fake-image-content');

    config([
        'app.url' => 'http://localhost:2000',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.public_app_url' => 'http://192.168.1.50:2000',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $invoice = Invoice::factory()->create([
        'merchant_name' => '7-Eleven',
        'total_amount' => '2.00',
        'payment_method' => PaymentMethod::Cash,
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'image_path' => 'receipts/wa_MSG123.jpg',
        'original_filename' => 'wa_MSG123.jpg',
    ]);

    Cache::put(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'), [
        'count' => 2,
        'token' => 'pending-token',
        'invoice_ids' => [$invoice->id],
    ], now()->addMinutes(5));

    $job = new class($invoice->id) extends SendWhatsAppDocumentParsedJob
    {
        public bool $released = false;

        public function release($delay = 0): void
        {
            $this->released = true;
        }
    };

    $job->handle(app(WhatsAppNotificationService::class));

    expect($job->released)->toBeTrue();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));

    Cache::forget(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'));

    $job->released = false;
    $job->handle(app(WhatsAppNotificationService::class));

    expect($job->released)->toBeFalse();

    $editUrl = WhatsAppPublicUrl::withRoot(
        fn (): string => InvoiceResource::getUrl('edit', ['record' => $invoice]),
    );

    Http::assertSent(function (Request $request) use ($editUrl): bool {
        $text = (string) ($request['text'] ?? '');

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Document parsed*')
            && str_contains($text, 'Merchant: *7-Eleven*')
            && str_contains($text, 'Total Amount: *RM 2.00*')
            && str_contains($text, 'Payment Method:')
            && str_contains($text, '*invoice edit*')
            && str_contains($text, $editUrl)
            && ! str_contains($text, 'wa_MSG123.jpg')
            && ! str_contains($text, '/storage/receipts/');
    });

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendMedia/')
        || str_contains($request->url(), '/message/sendButtons/'));
});

test('extract receipt data job does not dispatch document parsed for non-whatsapp invoices', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/manual.jpg', 'fake-image-content');

    config([
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
    ]);

    fakeSuccessfulOllamaResponse();
    $this->seed(LabelSeeder::class);

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'image_path' => 'receipts/manual.jpg',
        'original_filename' => 'manual.jpg',
    ]);

    $job = new ExtractReceiptDataJob($invoice->id);
    $job->handle(
        new OllamaService,
        new ReceiptParseNormalizer,
        new LabelMatcher,
    );

    expect($invoice->fresh()->status)->toBe('parsed');
    Queue::assertNotPushed(SendWhatsAppDocumentParsedJob::class);
});

test('extract receipt data job does not dispatch document parsed without whatsapp sender', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_NOSENDER.jpg', 'fake-image-content');

    config([
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
    ]);

    fakeSuccessfulOllamaResponse();
    $this->seed(LabelSeeder::class);

    $invoice = Invoice::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'whatsapp_sender' => null,
        'status' => 'pending',
        'image_path' => 'receipts/wa_NOSENDER.jpg',
        'original_filename' => 'wa_NOSENDER.jpg',
    ]);

    $job = new ExtractReceiptDataJob($invoice->id);
    $job->handle(
        new OllamaService,
        new ReceiptParseNormalizer,
        new LabelMatcher,
    );

    expect($invoice->fresh()->status)->toBe('parsed');
    Queue::assertNotPushed(SendWhatsAppDocumentParsedJob::class);
});
