<?php

declare(strict_types=1);

use App\Jobs\ProcessWhatsAppMediaJob;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_key' => 'tido-secret-key',
    ]);

    User::factory()->create(['phone' => '60123456789']);
});

test('whatsapp webhook rejects unauthorized requests', function () {
    $this->postJson('/api/webhooks/whatsapp', [], [
        'Authorization' => 'Bearer invalid-token',
    ])->assertStatus(401);
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
        'Authorization' => 'Bearer tido-secret-key',
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();
    expect(Invoice::count())->toBe(0);
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
        'Authorization' => 'Bearer tido-secret-key',
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    expect(Invoice::count())->toBe(0);
});

test('whatsapp webhook handles text queries for monthly spent', function () {
    Invoice::factory()->count(3)->create([
        'total_amount' => 50.00,
        'date_time' => now(),
        'status' => 'reviewed',
    ]);

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
        'Authorization' => 'Bearer tido-secret-key',
    ])->assertSuccessful();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], 'Monthly spending')
            && str_contains((string) $request['text'], 'Total spent:');
    });
});

test('whatsapp webhook allows self-chat fromMe when sender is allowlisted', function () {
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
        'Authorization' => 'Bearer tido-secret-key',
    ])->assertSuccessful();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], '*Help*')
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
        'Authorization' => 'Bearer tido-secret-key',
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessWhatsAppMediaJob::class, function (ProcessWhatsAppMediaJob $job): bool {
        return $job->senderNumber === '60123456789'
            && $job->remoteJid === '60123456789@s.whatsapp.net'
            && $job->messageId === 'MSG456'
            && $job->fromMe === false;
    });

    expect(Invoice::count())->toBe(0);
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
        'Authorization' => 'Bearer tido-secret-key',
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
        'Authorization' => 'Bearer tido-secret-key',
    ])->assertSuccessful();

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
        'Authorization' => 'Bearer tido-secret-key',
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();
});
