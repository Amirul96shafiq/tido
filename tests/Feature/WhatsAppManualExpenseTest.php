<?php

declare(strict_types=1);

use App\Jobs\ParseManualWhatsAppExpenseJob;
use App\Jobs\ProcessManualWhatsAppExpenseJob;
use App\Jobs\SendWhatsAppManualExpenseParsedJob;
use App\Jobs\SendWhatsAppManualExpenseReceivedAckJob;
use App\Models\Expense;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppManualExpenseReceivedDebouncer;
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
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution-api.test',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.document_received_debounce_seconds' => 3,
        'services.ollama.host' => 'http://ollama.test',
        'services.ollama.model' => 'test-model',
        'services.ollama.timeout' => 30,
    ]);

    User::factory()->create(['phone' => '60123456789']);

    Cache::flush();
});

test('whatsapp webhook dispatches process job for manual expense text', function () {
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
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessManualWhatsAppExpenseJob::class, function (ProcessManualWhatsAppExpenseJob $job) use ($text): bool {
        return $job->senderNumber === '60123456789'
            && $job->text === $text;
    });

    Http::assertNothingSent();
});

test('whatsapp help mentions manual expense format', function () {
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
        ...evolutionWebhookHeaders(),
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Help*')
            && str_contains((string) $request['text'], '*document(s)*')
            && str_contains((string) $request['text'], '*image(s)*')
            && str_contains((string) $request['text'], '*manual* to learn more')
            && str_contains((string) $request['text'], '*finance others* to learn more');
    });
});

test('whatsapp manual reply lists payment methods and format', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-MANUAL',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'manual',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Manual Approach*')
            && str_contains($text, 'ASNB Investment, FPX;')
            && str_contains($text, 'Payment method supported:')
            && str_contains($text, '- Cash');
    });
});

test('whatsapp finance others reply lists spending keywords', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-FINANCE',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'finance others',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Finance Keywords*')
            && str_contains($text, '*spend labels*')
            && str_contains($text, '*spend merchants*');
    });
});

test('process manual whatsapp expense job creates expense items and registers debounce', function () {
    Queue::fake();

    $text = "myNEWS Bayu Residensi;\nGARDENIA QUICKBITES CREAM ROLL, 1, 1.2;\nGARDENIA ORIG CLASSIC ENR.WHIT, 1, 3;";

    (new ProcessManualWhatsAppExpenseJob('60123456789', $text))->handle();

    $expense = Expense::query()->with('expenseItems')->first();

    expect($expense)->not->toBeNull()
        ->and($expense->merchant_name)->toBe('myNEWS Bayu Residensi')
        ->and((float) $expense->total_amount)->toBe(4.2)
        ->and((float) $expense->subtotal)->toBe(4.2)
        ->and($expense->currency)->toBe('MYR')
        ->and($expense->paymentMethod->slug)->toBe('cash')
        ->and($expense->source)->toBe('whatsapp')
        ->and($expense->whatsapp_sender)->toBe('60123456789')
        ->and($expense->status)->toBe('pending')
        ->and($expense->image_path)->toBeNull()
        ->and($expense->expenseItems)->toHaveCount(2)
        ->and($expense->expenseItems[0]->label_id)->toBeNull()
        ->and((float) $expense->expenseItems[0]->unit_price)->toBe(1.2)
        ->and((float) $expense->expenseItems[1]->unit_price)->toBe(3.0);

    $payload = Cache::get(WhatsAppManualExpenseReceivedDebouncer::cacheKey('60123456789'));

    expect($payload)->toBeArray()
        ->and($payload['count'])->toBe(1)
        ->and($payload['expense_ids'])->toContain($expense->id);

    Queue::assertPushed(SendWhatsAppManualExpenseReceivedAckJob::class, 1);
    Queue::assertNotPushed(ParseManualWhatsAppExpenseJob::class);
});

test('process job applies merchant payment token', function () {
    Queue::fake();

    $text = <<<'TEXT'
Kedai Makan Seri Ayu, qr;
Nasi + ikan keli, 1, 12;
Teh o ais, 1, 2.5;
TEXT;

    (new ProcessManualWhatsAppExpenseJob('60123456789', $text))->handle();

    $expense = Expense::query()->first();

    expect($expense)->not->toBeNull()
        ->and($expense->merchant_name)->toBe('Kedai Makan Seri Ayu')
        ->and($expense->paymentMethod->slug)->toBe('pay_with_qr')
        ->and((float) $expense->total_amount)->toBe(14.5);
});

test('process job creates multiple expenses from one message', function () {
    Queue::fake();

    $text = <<<'TEXT'
myNEWS Bayu Residensi;
GARDENIA QUICKBITES CREAM ROLL, 1, 1.2;

7-Eleven Malaysia Sdn. Bhd.;
Hausboom Grapple 325, 1, 2;
TEXT;

    (new ProcessManualWhatsAppExpenseJob('60123456789', $text))->handle();

    expect(Expense::count())->toBe(2);

    $payload = Cache::get(WhatsAppManualExpenseReceivedDebouncer::cacheKey('60123456789'));

    expect($payload['count'])->toBe(2)
        ->and($payload['expense_ids'])->toHaveCount(2);
});

test('manual expense received ack sends message and dispatches parse jobs', function () {
    Queue::fake();

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $sender = '60123456789';
    $expenseA = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => $sender,
        'status' => 'pending',
        'image_path' => null,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
    ]);
    $expenseB = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => $sender,
        'status' => 'pending',
        'image_path' => null,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
    ]);

    WhatsAppManualExpenseReceivedDebouncer::register($sender, $expenseA->id);
    $this->travel(1)->second();
    WhatsAppManualExpenseReceivedDebouncer::register($sender, $expenseB->id);

    $payload = Cache::get(WhatsAppManualExpenseReceivedDebouncer::cacheKey($sender));
    $token = $payload['token'];

    (new SendWhatsAppManualExpenseReceivedAckJob($sender, $token))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Manual expense received*')
            && str_contains((string) $request['text'], 'A total of *2* manual expense(s) saved and queued for AI parsing.');
    });

    expect(Cache::get(WhatsAppManualExpenseReceivedDebouncer::cacheKey($sender)))->toBeNull();

    Queue::assertPushed(ParseManualWhatsAppExpenseJob::class, 2);
});

test('parse manual whatsapp expense job applies labels and requires manual review', function () {
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

    $expense = Expense::factory()->create([
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

    $expense->expenseItems()->create([
        'description' => 'GARDENIA QUICKBITES CREAM ROLL',
        'quantity' => 1,
        'unit_price' => 1.20,
        'line_total' => 1.20,
        'label_id' => null,
    ]);
    $expense->expenseItems()->create([
        'description' => 'GARDENIA ORIG CLASSIC ENR.WHIT',
        'quantity' => 1,
        'unit_price' => 3.00,
        'line_total' => 3.00,
        'label_id' => null,
    ]);

    (new ParseManualWhatsAppExpenseJob($expense->id))->handle(
        app(OllamaService::class),
        app(LabelMatcher::class),
    );

    $expense->refresh()->load('expenseItems');

    $food = Label::query()->where('name', 'Food & Dining')->firstOrFail();
    $groceries = Label::query()->where('name', 'Groceries & Household')->firstOrFail();

    expect($expense->status)->toBe('requires_manual_review')
        ->and($expense->expenseItems[0]->label_id)->toBe($food->id)
        ->and($expense->expenseItems[1]->label_id)->toBe($groceries->id)
        ->and($expense->raw_ai_response)->toHaveKey('label_classification');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return str_contains($request->url(), '/api/generate')
            && ($body['format'] ?? null) === 'json'
            && ! array_key_exists('images', $body);
    });

    Queue::assertPushed(SendWhatsAppManualExpenseParsedJob::class, function (SendWhatsAppManualExpenseParsedJob $job) use ($expense): bool {
        return $job->expenseId === $expense->id;
    });
});

test('send manual expense parsed job sends whatsapp message with edit url', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $expense = Expense::factory()->create([
        'merchant_name' => 'myNEWS Bayu Residensi',
        'total_amount' => 4.20,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'requires_manual_review',
        'image_path' => null,
    ]);

    (new SendWhatsAppManualExpenseParsedJob($expense->id))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) use ($expense): bool {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Manual expense parsed*')
            && str_contains($text, 'myNEWS Bayu Residensi')
            && str_contains($text, 'RM 4.20')
            && str_contains($text, 'Cash')
            && str_contains($text, '/admin/expenses/'.$expense->id.'/edit');
    });
});
