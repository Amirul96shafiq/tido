<?php

declare(strict_types=1);

use App\Jobs\ParseManualWhatsAppInvoiceJob;
use App\Jobs\ProcessManualWhatsAppInvoiceJob;
use App\Jobs\SendWhatsAppManualInvoiceParsedJob;
use App\Jobs\SendWhatsAppManualInvoiceReceivedAckJob;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppManualInvoiceReceivedDebouncer;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PaymentMethodSeeder::class);

    config([
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.personal_number' => '60123456789',
        'services.evolution.personal_extra_numbers' => null,
        'services.evolution.document_received_debounce_seconds' => 3,
        'services.ollama.host' => 'http://ollama.test',
        'services.ollama.model' => 'test-model',
        'services.ollama.timeout' => 30,
    ]);

    Cache::flush();
});

test('whatsapp webhook dispatches process job for manual invoice text', function () {
    Queue::fake();
    Http::fake();

    $text = "myNEWS Bayu Residensi;\nGARDENIA QUICKBITES CREAM ROLL, 1, 1.2;\nGARDENIA ORIG CLASSIC ENR.WHIT, 1, 3;";

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-MANUAL-1',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => $text,
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'Authorization' => 'Bearer tido-secret-key',
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessManualWhatsAppInvoiceJob::class, function (ProcessManualWhatsAppInvoiceJob $job) use ($text): bool {
        return $job->senderNumber === '60123456789'
            && $job->text === $text;
    });

    Http::assertNothingSent();
});

test('whatsapp help mentions manual invoice format', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-HELP',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'Authorization' => 'Bearer tido-secret-key',
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Help*')
            && str_contains((string) $request['text'], 'manual invoice');
    });
});

test('process manual whatsapp invoice job creates invoice items and registers debounce', function () {
    Queue::fake();

    $text = "myNEWS Bayu Residensi;\nGARDENIA QUICKBITES CREAM ROLL, 1, 1.2;\nGARDENIA ORIG CLASSIC ENR.WHIT, 1, 3;";

    (new ProcessManualWhatsAppInvoiceJob('60123456789', $text))->handle();

    $invoice = Invoice::query()->with('invoiceItems')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->merchant_name)->toBe('myNEWS Bayu Residensi')
        ->and((float) $invoice->total_amount)->toBe(4.2)
        ->and((float) $invoice->subtotal)->toBe(4.2)
        ->and($invoice->currency)->toBe('MYR')
        ->and($invoice->paymentMethod->slug)->toBe('cash')
        ->and($invoice->source)->toBe('whatsapp')
        ->and($invoice->whatsapp_sender)->toBe('60123456789')
        ->and($invoice->status)->toBe('pending')
        ->and($invoice->image_path)->toBeNull()
        ->and($invoice->invoiceItems)->toHaveCount(2)
        ->and($invoice->invoiceItems[0]->label_id)->toBeNull()
        ->and((float) $invoice->invoiceItems[0]->unit_price)->toBe(1.2)
        ->and((float) $invoice->invoiceItems[1]->unit_price)->toBe(3.0);

    $payload = Cache::get(WhatsAppManualInvoiceReceivedDebouncer::cacheKey('60123456789'));

    expect($payload)->toBeArray()
        ->and($payload['count'])->toBe(1)
        ->and($payload['invoice_ids'])->toContain($invoice->id);

    Queue::assertPushed(SendWhatsAppManualInvoiceReceivedAckJob::class, 1);
    Queue::assertNotPushed(ParseManualWhatsAppInvoiceJob::class);
});

test('process job applies merchant payment token', function () {
    Queue::fake();

    $text = <<<'TEXT'
Kedai Makan Seri Ayu, qr;
Nasi + ikan keli, 1, 12;
Teh o ais, 1, 2.5;
TEXT;

    (new ProcessManualWhatsAppInvoiceJob('60123456789', $text))->handle();

    $invoice = Invoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->merchant_name)->toBe('Kedai Makan Seri Ayu')
        ->and($invoice->paymentMethod->slug)->toBe('pay_with_qr')
        ->and((float) $invoice->total_amount)->toBe(14.5);
});

test('process job creates multiple invoices from one message', function () {
    Queue::fake();

    $text = <<<'TEXT'
myNEWS Bayu Residensi;
GARDENIA QUICKBITES CREAM ROLL, 1, 1.2;

7-Eleven Malaysia Sdn. Bhd.;
Hausboom Grapple 325, 1, 2;
TEXT;

    (new ProcessManualWhatsAppInvoiceJob('60123456789', $text))->handle();

    expect(Invoice::count())->toBe(2);

    $payload = Cache::get(WhatsAppManualInvoiceReceivedDebouncer::cacheKey('60123456789'));

    expect($payload['count'])->toBe(2)
        ->and($payload['invoice_ids'])->toHaveCount(2);
});

test('manual invoice received ack sends message and dispatches parse jobs', function () {
    Queue::fake();

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';
    $invoiceA = Invoice::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => $sender,
        'status' => 'pending',
        'image_path' => null,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
    ]);
    $invoiceB = Invoice::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => $sender,
        'status' => 'pending',
        'image_path' => null,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
    ]);

    WhatsAppManualInvoiceReceivedDebouncer::register($sender, $invoiceA->id);
    $this->travel(1)->second();
    WhatsAppManualInvoiceReceivedDebouncer::register($sender, $invoiceB->id);

    $payload = Cache::get(WhatsAppManualInvoiceReceivedDebouncer::cacheKey($sender));
    $token = $payload['token'];

    (new SendWhatsAppManualInvoiceReceivedAckJob($sender, $token))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Manual invoice received*')
            && str_contains((string) $request['text'], 'A total of *2* manual invoice(s) saved and queued for AI parsing.');
    });

    expect(Cache::get(WhatsAppManualInvoiceReceivedDebouncer::cacheKey($sender)))->toBeNull();

    Queue::assertPushed(ParseManualWhatsAppInvoiceJob::class, 2);
});

test('parse manual whatsapp invoice job applies labels and requires manual review', function () {
    Queue::fake();
    $this->seed(LabelSeeder::class);

    Http::fake([
        '*/api/generate' => Http::response([
            'response' => json_encode([
                'items' => [
                    [
                        'description' => 'GARDENIA QUICKBITES CREAM ROLL',
                        'label' => 'Food & Dining',
                    ],
                    [
                        'description' => 'GARDENIA ORIG CLASSIC ENR.WHIT',
                        'label' => 'Groceries & Household',
                    ],
                ],
            ]),
        ]),
    ]);

    $invoice = Invoice::factory()->create([
        'merchant_name' => 'myNEWS Bayu Residensi',
        'total_amount' => 4.20,
        'subtotal' => 4.20,
        'currency' => 'MYR',
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
        'image_path' => null,
    ]);

    $invoice->invoiceItems()->create([
        'description' => 'GARDENIA QUICKBITES CREAM ROLL',
        'quantity' => 1,
        'unit_price' => 1.20,
        'line_total' => 1.20,
        'label_id' => null,
    ]);
    $invoice->invoiceItems()->create([
        'description' => 'GARDENIA ORIG CLASSIC ENR.WHIT',
        'quantity' => 1,
        'unit_price' => 3.00,
        'line_total' => 3.00,
        'label_id' => null,
    ]);

    (new ParseManualWhatsAppInvoiceJob($invoice->id))->handle(
        app(OllamaService::class),
        app(LabelMatcher::class),
    );

    $invoice->refresh()->load('invoiceItems');

    $food = Label::query()->where('name', 'Food & Dining')->firstOrFail();
    $groceries = Label::query()->where('name', 'Groceries & Household')->firstOrFail();

    expect($invoice->status)->toBe('requires_manual_review')
        ->and($invoice->invoiceItems[0]->label_id)->toBe($food->id)
        ->and($invoice->invoiceItems[1]->label_id)->toBe($groceries->id)
        ->and($invoice->raw_ai_response)->toHaveKey('label_classification');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return str_contains($request->url(), '/api/generate')
            && ($body['format'] ?? null) === 'json'
            && ! array_key_exists('images', $body);
    });

    Queue::assertPushed(SendWhatsAppManualInvoiceParsedJob::class, function (SendWhatsAppManualInvoiceParsedJob $job) use ($invoice): bool {
        return $job->invoiceId === $invoice->id;
    });
});

test('send manual invoice parsed job sends whatsapp message with edit url', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $invoice = Invoice::factory()->create([
        'merchant_name' => 'myNEWS Bayu Residensi',
        'total_amount' => 4.20,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'requires_manual_review',
        'image_path' => null,
    ]);

    (new SendWhatsAppManualInvoiceParsedJob($invoice->id))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) use ($invoice): bool {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Manual invoice parsed*')
            && str_contains($text, 'myNEWS Bayu Residensi')
            && str_contains($text, 'RM 4.20')
            && str_contains($text, 'Cash')
            && str_contains($text, '/admin/invoices/'.$invoice->id.'/edit');
    });
});
