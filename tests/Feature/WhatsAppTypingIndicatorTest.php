<?php

declare(strict_types=1);

use App\Jobs\ExtractReceiptDataJob;
use App\Jobs\MaintainWhatsAppTypingIndicatorJob;
use App\Models\Expense;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
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

test('typing indicator keeper sends presence and reschedules while expense is pending', function () {
    Queue::fake();
    Storage::fake('local');

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

test('typing indicator keeper stops when expense is no longer pending', function () {
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
