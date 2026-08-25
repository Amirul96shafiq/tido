<?php

declare(strict_types=1);

use App\Jobs\ProcessManualWhatsAppExpenseJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Jobs\ProcessWhatsAppTextReplyJob;
use App\Models\User;
use App\Support\WhatsAppWebhookIdempotency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function sec009UpsertPayload(
    string $messageId = 'MSG-TRUST-1',
    string $remoteJid = '60123456789@s.whatsapp.net',
    string $messageType = 'conversation',
    string $text = 'help',
): array {
    $message = match ($messageType) {
        'imageMessage' => ['imageMessage' => []],
        'documentMessage' => [
            'documentMessage' => [
                'mimetype' => 'application/pdf',
                'fileName' => 'receipt.pdf',
            ],
        ],
        'extendedTextMessage' => [
            'extendedTextMessage' => ['text' => $text],
        ],
        default => ['conversation' => $text],
    };

    return [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => $remoteJid,
                'fromMe' => false,
                'id' => $messageId,
            ],
            'messageType' => $messageType,
            'message' => $message,
        ],
    ];
}

beforeEach(function () {
    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_allowed_ips' => '127.0.0.1,::1',
        'services.evolution.webhook_per_ip_attempts_per_minute' => 60,
        'services.evolution.webhook_global_attempts_per_minute' => 60,
        'services.evolution.webhook_per_sender_attempts_per_minute' => 20,
        'services.evolution.webhook_max_body_bytes' => 262144,
    ]);

    Cache::flush();
    RateLimiter::clear('whatsapp-webhook:ip:127.0.0.1');
    RateLimiter::clear('whatsapp-webhook:global');
    RateLimiter::clear('whatsapp-webhook:sender:60123456789');

    User::factory()->create(['phone' => '60123456789']);
});

test('whatsapp webhook rejects non-allowlisted source ip', function (): void {
    Queue::fake();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/webhooks/whatsapp', sec009UpsertPayload(), evolutionWebhookHeaders())
        ->assertForbidden()
        ->assertJson(['error' => 'Forbidden']);

    Queue::assertNothingPushed();
});

test('whatsapp webhook rejects empty ip allowlist', function (): void {
    config(['services.evolution.webhook_allowed_ips' => '']);
    Queue::fake();

    $this->postJson('/api/webhooks/whatsapp', sec009UpsertPayload(), evolutionWebhookHeaders())
        ->assertForbidden()
        ->assertJson(['error' => 'Forbidden']);

    Queue::assertNothingPushed();
});

test('whatsapp webhook rejects oversized body', function (): void {
    config(['services.evolution.webhook_max_body_bytes' => 64]);
    Queue::fake();

    $payload = sec009UpsertPayload(text: str_repeat('a', 200));

    $this->postJson('/api/webhooks/whatsapp', $payload, evolutionWebhookHeaders())
        ->assertStatus(413)
        ->assertJson(['error' => 'Payload too large']);

    Queue::assertNothingPushed();
});

test('whatsapp webhook rejects group jid spoof of allowlisted number', function (): void {
    Queue::fake();
    Http::fake();

    $this->postJson(
        '/api/webhooks/whatsapp',
        sec009UpsertPayload(remoteJid: '60123456789@g.us'),
        evolutionWebhookHeaders(),
    )
        ->assertStatus(422)
        ->assertJson(['error' => 'Invalid payload']);

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('whatsapp webhook rejects missing message id', function (): void {
    Queue::fake();

    $payload = sec009UpsertPayload();
    unset($payload['data']['key']['id']);

    $this->postJson('/api/webhooks/whatsapp', $payload, evolutionWebhookHeaders())
        ->assertStatus(422)
        ->assertJson(['error' => 'Invalid payload']);

    Queue::assertNothingPushed();
});

test('whatsapp webhook rejects invalid message id characters', function (): void {
    Queue::fake();

    $this->postJson(
        '/api/webhooks/whatsapp',
        sec009UpsertPayload(messageId: 'MSG ID WITH SPACES'),
        evolutionWebhookHeaders(),
    )
        ->assertStatus(422)
        ->assertJson(['error' => 'Invalid payload']);

    Queue::assertNothingPushed();
});

test('whatsapp webhook returns duplicate for replayed message id', function (): void {
    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $payload = sec009UpsertPayload(messageId: 'MSG-REPLAY-1', text: 'help');

    $this->postJson('/api/webhooks/whatsapp', $payload, evolutionWebhookHeaders())
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Http::assertNothingSent();
    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, 1);

    $this->postJson('/api/webhooks/whatsapp', $payload, evolutionWebhookHeaders())
        ->assertSuccessful()
        ->assertJson(['status' => 'duplicate']);

    Http::assertNothingSent();
    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, 1);
});

test('whatsapp webhook duplicate media dispatch pushes only once', function (): void {
    Queue::fake();

    $payload = sec009UpsertPayload(
        messageId: 'MSG-MEDIA-DUP',
        messageType: 'imageMessage',
    );

    $this->postJson('/api/webhooks/whatsapp', $payload, evolutionWebhookHeaders())
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    $this->postJson('/api/webhooks/whatsapp', $payload, evolutionWebhookHeaders())
        ->assertSuccessful()
        ->assertJson(['status' => 'duplicate']);

    Queue::assertPushed(ProcessWhatsAppMediaJob::class, 1);
});

test('whatsapp webhook enforces per-ip rate limit without leaking secret or text', function (): void {
    config([
        'services.evolution.webhook_per_ip_attempts_per_minute' => 5,
        'services.evolution.webhook_global_attempts_per_minute' => 100,
    ]);

    RateLimiter::clear('whatsapp-webhook:ip:127.0.0.1');
    RateLimiter::clear('whatsapp-webhook:global');

    $secret = (string) config('services.evolution.webhook_secret');
    $conversation = 'secret-conversation-body-xyz';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(
            '/api/webhooks/whatsapp',
            sec009UpsertPayload(messageId: 'MSG-IP-'.$attempt, text: $conversation),
            evolutionWebhookHeaders(),
        )->assertSuccessful();
    }

    $response = $this->postJson(
        '/api/webhooks/whatsapp',
        sec009UpsertPayload(messageId: 'MSG-IP-OVERFLOW', text: $conversation),
        evolutionWebhookHeaders(),
    );

    $response->assertStatus(429)
        ->assertJson(['error' => 'Too many requests. Try again later.']);

    $encoded = (string) json_encode($response->json());

    expect($encoded)->not->toContain($secret)
        ->and($encoded)->not->toContain($conversation);
});

test('whatsapp webhook enforces global rate limit across ips', function (): void {
    config([
        'services.evolution.webhook_allowed_ips' => '10.8.0.1,10.8.0.2,10.8.0.3,10.8.0.4,10.8.0.5,10.8.0.6,10.8.0.7,10.8.0.8,10.8.0.9,10.8.0.10,10.8.0.99',
        'services.evolution.webhook_per_ip_attempts_per_minute' => 100,
        'services.evolution.webhook_global_attempts_per_minute' => 10,
    ]);

    RateLimiter::clear('whatsapp-webhook:global');

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $ip = '10.8.0.'.($attempt + 1);
        RateLimiter::clear('whatsapp-webhook:ip:'.$ip);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(
                '/api/webhooks/whatsapp',
                sec009UpsertPayload(messageId: 'MSG-GLOBAL-'.$attempt),
                evolutionWebhookHeaders(),
            )
            ->assertSuccessful();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.8.0.99'])
        ->postJson(
            '/api/webhooks/whatsapp',
            sec009UpsertPayload(messageId: 'MSG-GLOBAL-OVERFLOW'),
            evolutionWebhookHeaders(),
        )
        ->assertStatus(429)
        ->assertJson(['error' => 'Too many requests. Try again later.']);
});

test('whatsapp webhook enforces per-sender rate limit', function (): void {
    config(['services.evolution.webhook_per_sender_attempts_per_minute' => 3]);
    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    RateLimiter::clear('whatsapp-webhook:sender:60123456789');

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->postJson(
            '/api/webhooks/whatsapp',
            sec009UpsertPayload(messageId: 'MSG-SENDER-'.$attempt),
            evolutionWebhookHeaders(),
        )->assertSuccessful();
    }

    $this->postJson(
        '/api/webhooks/whatsapp',
        sec009UpsertPayload(messageId: 'MSG-SENDER-OVERFLOW'),
        evolutionWebhookHeaders(),
    )
        ->assertStatus(429)
        ->assertJson(['error' => 'Too many requests. Try again later.']);

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, 3);
    Http::assertNothingSent();
});

test('ignored non-upsert events do not claim message ids', function (): void {
    Queue::fake();

    $this->postJson('/api/webhooks/whatsapp', [
        'event' => 'connection.update',
        'data' => [
            'key' => [
                'remoteJid' => '60123456789@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-LATER-USED',
            ],
        ],
    ], evolutionWebhookHeaders())
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_event']);

    expect(WhatsAppWebhookIdempotency::claim('MSG-LATER-USED'))->toBeTrue();

    Cache::forget(WhatsAppWebhookIdempotency::cacheKey('MSG-LATER-USED'));

    $this->postJson(
        '/api/webhooks/whatsapp',
        sec009UpsertPayload(messageId: 'MSG-LATER-USED', messageType: 'imageMessage'),
        evolutionWebhookHeaders(),
    )
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessWhatsAppMediaJob::class, 1);
});

test('whatsapp webhook still dispatches manual expense after trust checks', function (): void {
    Queue::fake();
    Http::fake();

    $text = "myNEWS Bayu Residensi;\nGARDENIA QUICKBITES CREAM ROLL, 1, 1.2;";

    $this->postJson(
        '/api/webhooks/whatsapp',
        sec009UpsertPayload(messageId: 'MSG-MANUAL-TRUST', text: $text),
        evolutionWebhookHeaders(),
    )
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessManualWhatsAppExpenseJob::class, function (ProcessManualWhatsAppExpenseJob $job): bool {
        return $job->messageId === 'MSG-MANUAL-TRUST';
    });
});

test('unauthorized requests still return 401 before schema errors', function (): void {
    $this->postJson('/api/webhooks/whatsapp', [], [
        'Authorization' => 'Bearer invalid-token',
    ])->assertUnauthorized();
});
