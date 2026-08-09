<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\SendWhatsAppDocumentParsedJob;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppPublicUrl;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
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
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
    ]);

    fakeSuccessfulOllamaResponse();
    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $expense = Expense::create([
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

    $job = new ExtractReceiptDataJob($expense->id);
    app()->call([$job, 'handle']);

    expect($expense->fresh()->status)->toBe('parsed');

    Queue::assertPushed(SendWhatsAppDocumentParsedJob::class, function (SendWhatsAppDocumentParsedJob $job) use ($expense): bool {
        return $job->expenseId === $expense->id;
    });

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
});

test('extract receipt data job dispatches a needs review whatsapp result when conversion fails', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_USD-FAIL.jpg', 'fake-image-content');

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
        'services.currencyapi.api_key' => 'test-key',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
        'services.currencyapi.retry_delays' => [0],
        'cache.default' => 'array',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'Cursor',
                'invoice_number' => 'USD-FAIL',
                'date_time' => '2026-07-08 00:00:00',
                'subtotal' => 20.00,
                'total_tax' => 0.00,
                'discount_total' => 14.00,
                'rounding_amount' => 0.00,
                'total_amount' => 6.00,
                'currency' => 'USD',
                'payment_method' => 'cash',
                'items' => [[
                    'description' => 'Cursor Pro',
                    'quantity' => 1,
                    'unit_price' => 20.00,
                    'line_total' => 20.00,
                    'label' => 'Food & Dining',
                ]],
            ]),
        ]),
        'https://currencyapi.test/v3/historical*' => Http::failedConnection(),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $expense = Expense::create([
        'merchant_name' => 'Pending AI Extraction...',
        'date_time' => now(),
        'subtotal' => 0.00,
        'total_tax' => 0.00,
        'total_amount' => 0.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
        'image_path' => 'receipts/wa_USD-FAIL.jpg',
        'original_filename' => 'wa_USD-FAIL.jpg',
    ]);

    app()->call([new ExtractReceiptDataJob($expense->id), 'handle']);

    expect($expense->fresh()->status)->toBe('requires_manual_review')
        ->and($expense->fresh()->currency)->toBe('USD')
        ->and($expense->fresh()->currency_conversion_status)->toBe('failed');

    Queue::assertPushed(SendWhatsAppDocumentParsedJob::class, function (SendWhatsAppDocumentParsedJob $job) use ($expense): bool {
        return $job->expenseId === $expense->id;
    });

    Cache::forget(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'));
    (new SendWhatsAppDocumentParsedJob($expense->id))->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request): bool {
        $text = (string) ($request['text'] ?? '');

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Document needs review*')
            && str_contains($text, 'Total Amount: *USD 6.00*');
    });
});

test('document parsed job waits while document received ack is pending then sends text url links', function () {
    Storage::fake('local');
    Storage::put('receipts/wa_MSG123.jpg', 'fake-image-content');

    config([
        'app.url' => 'http://localhost:2000',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.public_app_url' => 'http://192.168.1.50:2000',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $this->seed(PaymentMethodSeeder::class);

    $expense = Expense::factory()->create([
        'merchant_name' => '7-Eleven',
        'total_amount' => '2.00',
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'image_path' => 'receipts/wa_MSG123.jpg',
        'original_filename' => 'wa_MSG123.jpg',
    ]);

    Cache::put(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'), [
        'count' => 2,
        'token' => 'pending-token',
        'expense_ids' => [$expense->id],
    ], now()->addMinutes(5));

    $job = new class($expense->id) extends SendWhatsAppDocumentParsedJob
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
        fn (): string => ExpenseResource::getUrl('edit', ['record' => $expense]),
    );

    Http::assertSent(function (Request $request) use ($editUrl): bool {
        $text = (string) ($request['text'] ?? '');

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Document parsed*')
            && str_contains($text, 'Merchant: *7-Eleven*')
            && str_contains($text, 'Total Amount: *RM 2.00*')
            && str_contains($text, 'Payment Method:')
            && str_contains($text, '*expense edit*')
            && str_contains($text, $editUrl)
            && ! str_contains($text, 'wa_MSG123.jpg')
            && ! str_contains($text, '/storage/receipts/');
    });

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendMedia/')
        || str_contains($request->url(), '/message/sendButtons/'));
});

test('extract receipt data job does not dispatch document parsed for non-whatsapp expenses', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/manual.jpg', 'fake-image-content');

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
    ]);

    fakeSuccessfulOllamaResponse();
    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $expense = Expense::create([
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

    $job = new ExtractReceiptDataJob($expense->id);
    app()->call([$job, 'handle']);

    expect($expense->fresh()->status)->toBe('parsed');
    Queue::assertNotPushed(SendWhatsAppDocumentParsedJob::class);
});

test('extract receipt data job does not dispatch document parsed without whatsapp sender', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_NOSENDER.jpg', 'fake-image-content');

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
    ]);

    fakeSuccessfulOllamaResponse();
    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $expense = Expense::create([
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

    $job = new ExtractReceiptDataJob($expense->id);
    app()->call([$job, 'handle']);

    expect($expense->fresh()->status)->toBe('parsed');
    Queue::assertNotPushed(SendWhatsAppDocumentParsedJob::class);
});
