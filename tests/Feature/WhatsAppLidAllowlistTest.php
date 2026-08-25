<?php

declare(strict_types=1);

use App\Jobs\ProcessWhatsAppTextReplyJob;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use App\Support\PhoneNumber;
use App\Support\WhatsAppLid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_allowed_ips' => '127.0.0.1,::1',
    ]);

    Cache::flush();
    RateLimiter::clear('whatsapp-webhook:ip:127.0.0.1');
    RateLimiter::clear('whatsapp-webhook:global');

    User::factory()->create([
        'phone' => '60123456789',
        'whatsapp_lid' => null,
    ]);
});

test('whatsapp lid normalizes lid jids and rejects phone numbers', function () {
    expect(WhatsAppLid::normalize('3693839708391@lid'))->toBe('3693839708391')
        ->and(WhatsAppLid::normalize('3693839708391'))->toBe('3693839708391')
        ->and(WhatsAppLid::normalize('60123456789@s.whatsapp.net'))->toBeNull()
        ->and(WhatsAppLid::normalize('60123456789'))->toBeNull()
        ->and(WhatsAppLid::isLidIdentifier('3693839708391@lid'))->toBeTrue()
        ->and(WhatsAppLid::isLidIdentifier('60123456789@s.whatsapp.net'))->toBeFalse();
});

test('linking a lid to primary allows webhook replies for that lid sender', function () {
    WhatsAppLid::link('3693839708391@lid', 'primary');

    expect(PhoneNumber::resolveAllowlistedSenderPhone('3693839708391@lid'))->toBe('60123456789')
        ->and(PhoneNumber::isAllowedWhatsAppSender('3693839708391@lid'))->toBeTrue()
        ->and(User::query()->whereKey(1)->value('whatsapp_lid'))->toBe('3693839708391');

    Queue::fake();
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $this->postJson('/api/webhooks/whatsapp', [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '3693839708391@lid',
                'fromMe' => false,
                'id' => 'MSG-LID-HELP',
                'addressingMode' => 'lid',
            ],
            'pushName' => 'Primary User',
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ], [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Http::assertNothingSent();

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, function (ProcessWhatsAppTextReplyJob $job): bool {
        return $job->senderNumber === '60123456789'
            && $job->originalText === 'help';
    });

    $job = new ProcessWhatsAppTextReplyJob('60123456789', 'help', 'MSG-LID-HELP');
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && ($request['number'] === '60123456789@s.whatsapp.net' || $request['number'] === '60123456789')
            && str_contains((string) $request['text'], '*Help*');
    });
});

test('unlinked lid senders are ignored and remembered as pending', function () {
    Http::fake();

    $this->postJson('/api/webhooks/whatsapp', [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '3693839708391@lid',
                'fromMe' => false,
                'id' => 'MSG-LID-PENDING',
                'addressingMode' => 'lid',
            ],
            'pushName' => 'Unknown Lid User',
            'messageType' => 'conversation',
            'message' => [
                'conversation' => 'help',
            ],
        ],
    ], [
        ...evolutionWebhookHeaders(),
    ])
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Http::assertNothingSent();

    expect(WhatsAppLid::pendingUnlinked())->toHaveCount(1)
        ->and(WhatsAppLid::pendingUnlinked()[0]['lid'])->toBe('3693839708391')
        ->and(WhatsAppLid::pendingUnlinked()[0]['push_name'])->toBe('Unknown Lid User');
});

test('linking a lid to an allowlisted family member resolves that phone', function () {
    $member = FamilyMember::factory()->create([
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    WhatsAppLid::link('5556667778889', 'family:'.$member->id);

    expect(PhoneNumber::resolveAllowlistedSenderPhone('5556667778889@lid'))->toBe('60111111111')
        ->and($member->fresh()->whatsapp_lid)->toBe('5556667778889');
});
