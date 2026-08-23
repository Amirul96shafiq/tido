<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\MaintainWhatsAppSenderTypingIndicatorJob;
use App\Jobs\MaintainWhatsAppTypingIndicatorJob;
use App\Jobs\SendWhatsAppDocumentParsedJob;
use App\Jobs\SendWhatsAppDocumentReceivedAckJob;
use App\Models\Expense;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppTypingSession;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.whatsapp_typing_enabled' => true,
        'services.evolution.whatsapp_typing_refresh_seconds' => 15,
        'services.evolution.whatsapp_typing_delay_ms' => 20000,
    ]);

    User::factory()->create(['phone' => '60123456789']);
});

test('extract receipt data job dispatches typing indicator for whatsapp pending expenses', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_typing.jpg', 'fake-image-content');

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
        'image_path' => 'receipts/wa_typing.jpg',
        'original_filename' => 'wa_typing.jpg',
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'document_classification' => 'receipt',
                'merchant_name' => '7-Eleven',
                'invoice_number' => 'INV-1',
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
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    app()->call([new ExtractReceiptDataJob($expense->id), 'handle']);

    expect(WhatsAppTypingSession::isActive($expense->id))->toBeTrue();

    Queue::assertPushed(MaintainWhatsAppTypingIndicatorJob::class, function (MaintainWhatsAppTypingIndicatorJob $job) use ($expense): bool {
        return $job->expenseId === $expense->id;
    });
});

test('extract receipt data job does not dispatch typing indicator for manual uploads', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/manual.jpg', 'fake-image-content');

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

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'document_classification' => 'receipt',
                'merchant_name' => '7-Eleven',
                'invoice_number' => 'INV-1',
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
    ]);

    app()->call([new ExtractReceiptDataJob($expense->id), 'handle']);

    Queue::assertNotPushed(MaintainWhatsAppTypingIndicatorJob::class);
    expect(WhatsAppTypingSession::isActive($expense->id))->toBeFalse();
});

test('extract receipt data job does not dispatch typing indicator when whatsapp sender is missing', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_no_sender.jpg', 'fake-image-content');

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
        'image_path' => 'receipts/wa_no_sender.jpg',
        'original_filename' => 'wa_no_sender.jpg',
    ]);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'document_classification' => 'receipt',
                'merchant_name' => '7-Eleven',
                'invoice_number' => 'INV-1',
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
    ]);

    app()->call([new ExtractReceiptDataJob($expense->id), 'handle']);

    Queue::assertNotPushed(MaintainWhatsAppTypingIndicatorJob::class);
});

test('document received ack job activates typing session and dispatches keeper per expense without blocking on sendTyping', function () {
    Queue::fake();

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';

    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-1',
        'expense_id' => 101,
        'filename' => 'one.jpg',
        'mime_type' => 'image/jpeg',
        'page_count' => null,
        'status' => 'accepted',
        'reason' => null,
    ]);
    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-2',
        'expense_id' => 102,
        'filename' => 'two.jpg',
        'mime_type' => 'image/jpeg',
        'page_count' => null,
        'status' => 'accepted',
        'reason' => null,
    ]);

    $payload = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));

    (new SendWhatsAppDocumentReceivedAckJob($sender, $payload['token']))
        ->handle(app(WhatsAppNotificationService::class));

    expect(WhatsAppTypingSession::isActive(101))->toBeTrue()
        ->and(WhatsAppTypingSession::isActive(102))->toBeTrue()
        ->and(WhatsAppTypingSession::sender(101))->toBe($sender);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/chat/sendPresence/'));

    Queue::assertPushed(ExtractReceiptDataJob::class, 2);
    Queue::assertPushed(MaintainWhatsAppTypingIndicatorJob::class, 2);
});

test('document received ack job does not re-dispatch expense keeper when session already active', function () {
    Queue::fake();

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';

    WhatsAppDocumentReceivedDebouncer::register($sender, [
        'message_id' => 'MSG-1',
        'expense_id' => 101,
        'filename' => 'one.jpg',
        'mime_type' => 'image/jpeg',
        'page_count' => null,
        'status' => 'accepted',
        'reason' => null,
    ]);

    $payload = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($sender));

    WhatsAppTypingSession::activate(101, $sender);
    MaintainWhatsAppTypingIndicatorJob::dispatch(101);

    (new SendWhatsAppDocumentReceivedAckJob($sender, $payload['token']))
        ->handle(app(WhatsAppNotificationService::class));

    Queue::assertPushed(MaintainWhatsAppTypingIndicatorJob::class, 1);
    Queue::assertPushed(ExtractReceiptDataJob::class, 1);
});

test('extract receipt data job does not re-dispatch keeper when typing session already active', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::put('receipts/wa_typing.jpg', 'fake-image-content');

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
        'image_path' => 'receipts/wa_typing.jpg',
        'original_filename' => 'wa_typing.jpg',
    ]);

    WhatsAppTypingSession::activate($expense->id, '60123456789');

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'document_classification' => 'receipt',
                'merchant_name' => '7-Eleven',
                'invoice_number' => 'INV-1',
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
    ]);

    app()->call([new ExtractReceiptDataJob($expense->id), 'handle']);

    Queue::assertNotPushed(MaintainWhatsAppTypingIndicatorJob::class);
});

test('sender typing indicator keeper sends presence and reschedules while sender session is active', function () {
    Queue::fake();

    WhatsAppTypingSession::activateSender('60123456789');

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    (new MaintainWhatsAppSenderTypingIndicatorJob('60123456789'))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/chat/sendPresence/tido')
        && data_get($request->data(), 'presence') === 'composing');

    Queue::assertPushed(MaintainWhatsAppSenderTypingIndicatorJob::class, function (MaintainWhatsAppSenderTypingIndicatorJob $job): bool {
        return $job->senderNumber === '60123456789';
    });
});

test('sender typing indicator keeper stops when sender session is inactive', function () {
    Queue::fake();

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    (new MaintainWhatsAppSenderTypingIndicatorJob('60123456789'))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertNothingSent();
    Queue::assertNotPushed(MaintainWhatsAppSenderTypingIndicatorJob::class);
});

test('typing indicator keeper sends presence and reschedules while typing session is active', function () {
    Queue::fake();

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
        'image_path' => 'receipts/wa_typing.jpg',
        'original_filename' => 'wa_typing.jpg',
    ]);

    WhatsAppTypingSession::activate($expense->id, '60123456789');

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    (new MaintainWhatsAppTypingIndicatorJob($expense->id))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/chat/sendPresence/tido')
        && data_get($request->data(), 'presence') === 'composing');

    Queue::assertPushed(MaintainWhatsAppTypingIndicatorJob::class, function (MaintainWhatsAppTypingIndicatorJob $job) use ($expense): bool {
        return $job->expenseId === $expense->id;
    });
});

test('typing indicator keeper continues after expense status leaves pending while session active', function () {
    Queue::fake();

    $expense = Expense::create([
        'merchant_name' => '7-Eleven',
        'date_time' => now(),
        'subtotal' => 2.00,
        'total_tax' => 0.00,
        'total_amount' => 2.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'image_path' => 'receipts/wa_typing.jpg',
        'original_filename' => 'wa_typing.jpg',
    ]);

    WhatsAppTypingSession::activate($expense->id, '60123456789');

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    (new MaintainWhatsAppTypingIndicatorJob($expense->id))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/chat/sendPresence/tido'));
    Queue::assertPushed(MaintainWhatsAppTypingIndicatorJob::class);
});

test('typing indicator keeper stops when typing session is inactive', function () {
    Queue::fake();

    $expense = Expense::create([
        'merchant_name' => '7-Eleven',
        'date_time' => now(),
        'subtotal' => 2.00,
        'total_tax' => 0.00,
        'total_amount' => 2.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'image_path' => 'receipts/wa_typing.jpg',
        'original_filename' => 'wa_typing.jpg',
    ]);

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    (new MaintainWhatsAppTypingIndicatorJob($expense->id))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertNothingSent();
    Queue::assertNotPushed(MaintainWhatsAppTypingIndicatorJob::class);
});

test('document parsed job deactivates typing session before sending text', function () {
    Storage::fake('local');
    Storage::put('receipts/wa_parsed.jpg', 'fake-image-content');

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $this->seed(PaymentMethodSeeder::class);

    $expense = Expense::factory()->create([
        'merchant_name' => '7-Eleven',
        'total_amount' => '2.00',
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'image_path' => 'receipts/wa_parsed.jpg',
        'original_filename' => 'wa_parsed.jpg',
    ]);

    WhatsAppTypingSession::activate($expense->id, '60123456789');

    (new SendWhatsAppDocumentParsedJob($expense->id))->handle(app(WhatsAppNotificationService::class));

    expect(WhatsAppTypingSession::isActive($expense->id))->toBeFalse();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
});

test('extract receipt data job failed deactivates typing session for whatsapp expenses', function () {
    Storage::fake('local');
    Storage::put('receipts/wa_fail.jpg', 'fake-image-content');

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
        'image_path' => 'receipts/wa_fail.jpg',
        'original_filename' => 'wa_fail.jpg',
    ]);

    WhatsAppTypingSession::activate($expense->id, '60123456789');

    (new ExtractReceiptDataJob($expense->id))->failed(new RuntimeException('OCR failed'));

    expect(WhatsAppTypingSession::isActive($expense->id))->toBeFalse()
        ->and($expense->fresh()->status)->toBe('requires_manual_review');
});

test('typing indicator keeper respects whatsapp typing enabled config', function () {
    Queue::fake();
    config(['services.evolution.whatsapp_typing_enabled' => false]);

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
        'image_path' => 'receipts/wa_typing.jpg',
        'original_filename' => 'wa_typing.jpg',
    ]);

    WhatsAppTypingSession::activate($expense->id, '60123456789');

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    (new MaintainWhatsAppTypingIndicatorJob($expense->id))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertNothingSent();
    Queue::assertNotPushed(MaintainWhatsAppTypingIndicatorJob::class);
});

test('typing indicator keeper declares evolution-send rate limited middleware', function () {
    $job = new MaintainWhatsAppTypingIndicatorJob(1);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class);

    $limiterName = (new ReflectionProperty($middleware[0], 'limiterName'))->getValue($middleware[0]);

    expect($limiterName)->toBe('evolution-send');
});
