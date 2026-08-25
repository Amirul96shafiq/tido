<?php

declare(strict_types=1);

use App\Jobs\MaintainWhatsAppSenderTypingIndicatorJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Jobs\ProcessWhatsAppTextReplyJob;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppTypingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_allowed_ips' => '127.0.0.1,::1',
        'services.evolution.whatsapp_typing_enabled' => true,
    ]);

    Cache::flush();
    RateLimiter::clear('whatsapp-webhook:ip:127.0.0.1');
    RateLimiter::clear('whatsapp-webhook:global');

    User::factory()->create(['phone' => '60123456789']);
});

test('whatsapp webhook rejects unauthorized requests', function (): void {
    $this->postJson('/api/webhooks/whatsapp', [], [
        'Authorization' => 'Bearer invalid-token',
    ])->assertUnauthorized();
});

test('whatsapp webhook accepts only the dedicated bearer secret', function (): void {
    $this->postJson('/api/webhooks/whatsapp', [], [
        'Authorization' => 'Bearer '.(string) config('services.evolution.api_key'),
    ])->assertUnauthorized();

    $this->postJson('/api/webhooks/whatsapp', [], [
        'Authorization' => (string) config('services.evolution.webhook_secret'),
    ])->assertUnauthorized();

    $this->postJson('/api/webhooks/whatsapp?token='.rawurlencode((string) config('services.evolution.webhook_secret')), [])
        ->assertUnauthorized();
});

test('whatsapp webhook rejects equal outbound and inbound credentials', function (): void {
    $secret = 'test-evolution-shared-secret-0123456789abcdef0123456789abcdef';

    config([
        'services.evolution.api_key' => $secret,
        'services.evolution.webhook_secret' => $secret,
    ]);

    Queue::fake();
    Http::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-EQUAL-SECRETS',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'Authorization' => 'Bearer '.$secret,
    ])->assertUnauthorized();

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('whatsapp webhook rejects an invalid configured secret', function (): void {
    config(['services.evolution.webhook_secret' => 'change-me']);

    $this->postJson('/api/webhooks/whatsapp', [], evolutionWebhookHeaders())
        ->assertUnauthorized();
});

test('whatsapp webhook ignores non-allowlisted senders without replying', function () {
    Http::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60199999999@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-STRANGER',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'spend',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();
    expect(Expense::count())->toBe(0);
});

test('whatsapp webhook ignores strangers image uploads', function () {
    Storage::fake('local');
    Queue::fake();
    Http::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60188888888@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-STRANGER-IMG',
            ],
            'messageType' => 'imageMessage',
            'message' => [
                'imageMessage' => [],
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    expect(Expense::count())->toBe(0);
});

test('whatsapp webhook handles text queries for monthly spent', function () {
    Expense::factory()->count(3)->create([
        'total_amount' => 50.00,
        'date_time' => now(),
        'status' => 'reviewed',
    ]);

    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG123',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'How much did I spend this month?',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Http::assertNothingSent();

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, function (ProcessWhatsAppTextReplyJob $job): bool {
        return $job->senderNumber === '60123456789'
            && $job->originalText === 'How much did I spend this month?';
    });

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'How much did I spend this month?', 'MSG-WEBHOOK-SPEND');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], 'Monthly Spending')
            && str_contains((string) $request['text'], 'Total spent:')
            && str_contains((string) $request['text'], 'Receipts:');
    });
});

test('whatsapp webhook handles spend labels sub-command', function () {
    Expense::unsetEventDispatcher();

    $label = Label::factory()->create([
        'name' => 'Transport',
        'slug' => 'transport',
    ]);

    $expense = Expense::create([
        'merchant_name' => 'Petronas',
        'invoice_number' => 'INV-FUEL',
        'receipt_hash' => 'hash-fuel-001',
        'date_time' => now()->copy()->startOfMonth()->addDay(),
        'subtotal' => 60.00,
        'total_tax' => 0.00,
        'total_amount' => 60.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'RON95',
        'quantity' => 1,
        'unit_price' => 60.00,
        'line_total' => 60.00,
    ]);

    Expense::setEventDispatcher(app('events'));

    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-LABELS',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'spend labels',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Http::assertNothingSent();

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, function (ProcessWhatsAppTextReplyJob $job): bool {
        return $job->senderNumber === '60123456789'
            && $job->originalText === 'spend labels';
    });

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'spend labels', 'MSG-WEBHOOK-LABELS');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Spending by Label*')
            && str_contains($text, '*Transport*');
    });
});

test('whatsapp webhook handles spend recurrings sub-command', function () {
    $recurring = Recurring::factory()->create(['title' => 'Astro']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => now()->copy()->startOfMonth()->addDays(10)->toDateString(),
        'expected_amount' => 99.00,
    ]);

    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-RECURRINGS',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'spend recurrings',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, function (ProcessWhatsAppTextReplyJob $job): bool {
        return $job->senderNumber === '60123456789'
            && $job->originalText === 'spend recurrings';
    });

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'spend recurrings', 'MSG-WEBHOOK-RECURRINGS');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, '*Recurring Payments*')
            && str_contains($text, '*Astro*');
    });
});


test('whatsapp webhook allows self-chat fromMe when sender is allowlisted', function () {
    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'MSG-SELF',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Http::assertNothingSent();

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, 1);

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'help', 'MSG-WEBHOOK-HELP');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Help*')
            && str_contains((string) $request['text'], '*document(s)*')
            && str_contains((string) $request['text'], '— Powered by *tido*');
    });
});

test('whatsapp webhook accepts image message and dispatches media job', function () {
    Queue::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG456',
            ],
            'messageType' => 'imageMessage',
            'message' => [
                'imageMessage' => [],
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessWhatsAppMediaJob::class, function (ProcessWhatsAppMediaJob $job): bool {
        return $job->senderNumber === '60123456789'
            && $job->remoteJid === '60123456789@s.whatsapp.net'
            && $job->messageId === 'MSG456'
            && $job->fromMe === false
            && $job->mediaType === 'image'
            && $job->declaredMimeType === 'image/jpeg';
    });

    Queue::assertPushed(MaintainWhatsAppSenderTypingIndicatorJob::class, fn (MaintainWhatsAppSenderTypingIndicatorJob $job): bool => $job->senderNumber === '60123456789');
    expect(WhatsAppTypingSession::isSenderActive('60123456789'))->toBeTrue();

    expect(Expense::count())->toBe(0);
});

test('whatsapp webhook accepts a PDF document and dispatches its metadata', function () {
    Queue::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-PDF',
            ],
            'messageType' => 'documentMessage',
            'message' => [
                'documentMessage' => [
                    'mimetype' => 'application/pdf',
                    'fileName' => '../receipt.pdf',
                ],
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessWhatsAppMediaJob::class, function (ProcessWhatsAppMediaJob $job): bool {
        return $job->messageId === 'MSG-PDF'
            && $job->mediaType === 'pdf'
            && $job->declaredMimeType === 'application/pdf'
            && $job->originalFilename === 'receipt.pdf';
    });
});

test('whatsapp webhook ignores non-PDF document messages', function () {
    Queue::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-DOCX',
            ],
            'messageType' => 'documentMessage',
            'message' => [
                'documentMessage' => [
                    'mimetype' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'fileName' => 'invoice.docx',
                ],
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_document_type']);

    Queue::assertNothingPushed();
});

test('whatsapp webhook denies all senders when no profile or family allowlist exists', function () {
    User::query()->update(['phone' => null]);
    Http::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-NO-ALLOW',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'spend',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();
});

test('whatsapp webhook allows allowlisted family members to interact with the bot', function () {
    FamilyMember::factory()->create([
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60111111111@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-EXTRA',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Http::assertNothingSent();

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, function (ProcessWhatsAppTextReplyJob $job): bool {
        return $job->senderNumber === '60111111111'
            && $job->originalText === 'help';
    });

    $job = new ProcessWhatsAppTextReplyJob('60111111111', 'help', 'MSG-WEBHOOK-OTHER');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Help*');
    });
});

test('whatsapp webhook ignores family members with allowlist disabled', function () {
    FamilyMember::factory()->notAllowlisted()->create([
        'phone' => '60111111111',
    ]);
    Http::fake();

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '60111111111@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-DISABLED',
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();
});
