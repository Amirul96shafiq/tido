<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.instance_name' => 'tido',
    ]);
});

test('isWhatsAppNumber returns true when evolution reports exists', function () {
    Http::fake([
        '*/chat/whatsappNumbers/*' => Http::response([
            [
                'exists' => true,
                'jid' => '60123456789@s.whatsapp.net',
                'number' => '60123456789',
            ],
        ]),
    ]);

    expect(app(WhatsAppNotificationService::class)->isWhatsAppNumber('60123456789'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/chat/whatsappNumbers/tido')
        && data_get($request->data(), 'numbers.0') === '60123456789'
        && ($request->header('apikey')[0] ?? null) === config('services.evolution.api_key'));
});

test('isWhatsAppNumber returns false when evolution reports missing', function () {
    Http::fake([
        '*/chat/whatsappNumbers/*' => Http::response([
            [
                'exists' => false,
                'number' => '6011163307051',
            ],
        ]),
    ]);

    expect(app(WhatsAppNotificationService::class)->isWhatsAppNumber('6011163307051'))->toBeFalse();
});

test('isWhatsAppNumber returns null when check request fails', function () {
    Http::fake([
        '*/chat/whatsappNumbers/*' => Http::response(['error' => 'unavailable'], 503),
    ]);

    expect(app(WhatsAppNotificationService::class)->isWhatsAppNumber('60123456789'))->toBeNull();
});

test('sendMessageResult classifies not on whatsapp failures', function () {
    User::factory()->create(['phone' => '60123456789']);

    Http::fake([
        '*/message/sendText/*' => Http::response([
            'error' => ['message' => 'The number does not exist on WhatsApp'],
        ], 400),
    ]);

    $result = app(WhatsAppNotificationService::class)
        ->sendMessageResult('60123456789', 'hello');

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toBe('not_on_whatsapp')
        ->and($result->detail)->toContain('does not exist');
});

test('sendMessageResult succeeds for accepted sendText', function () {
    User::factory()->create(['phone' => '60123456789']);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    $result = app(WhatsAppNotificationService::class)
        ->sendMessageResult('60123456789', 'hello');

    expect($result->ok)->toBeTrue()
        ->and($result->reason)->toBe('ok');
});

test('sendMessageResult does not call evolution for numbers outside the contact allowlist', function () {
    User::factory()->create(['phone' => '601116330705']);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    $result = app(WhatsAppNotificationService::class)
        ->sendMessageResult('60127121550', 'Recurring payment summary');

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toBe('not_allowlisted');

    Http::assertNothingSent();
});

test('sendTyping hits evolution sendPresence with composing presence', function () {
    User::factory()->create(['phone' => '60123456789']);

    config(['services.evolution.whatsapp_typing_delay_ms' => 18000]);

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    $result = app(WhatsAppNotificationService::class)->sendTyping('60123456789');

    expect($result->ok)->toBeTrue()
        ->and($result->reason)->toBe('ok');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/chat/sendPresence/tido')
            && data_get($request->data(), 'number') === '60123456789@s.whatsapp.net'
            && data_get($request->data(), 'presence') === 'composing'
            && data_get($request->data(), 'delay') === 18000
            && ($request->header('apikey')[0] ?? null) === config('services.evolution.api_key');
    });
});

test('sendTyping returns failure when evolution rejects presence request', function () {
    User::factory()->create(['phone' => '60123456789']);

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['error' => 'instance disconnected'], 503),
    ]);

    $result = app(WhatsAppNotificationService::class)->sendTyping('60123456789');

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toBe('presence_failed')
        ->and($result->status)->toBe(503);
});

test('sendTyping does not call evolution for numbers outside the contact allowlist', function () {
    User::factory()->create(['phone' => '601116330705']);

    Http::fake([
        '*/chat/sendPresence/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    $result = app(WhatsAppNotificationService::class)->sendTyping('60127121550');

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toBe('not_allowlisted');

    Http::assertNothingSent();
});

test('sendMessageResult restores a closed socket and retries sendText', function () {
    config([
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
    ]);

    User::factory()->create(['phone' => '60123456789']);
    Cache::flush();

    Http::fake([
        '*/message/sendText/*' => Http::sequence()
            ->push([
                'status' => 500,
                'error' => 'Internal Server Error',
                'response' => ['message' => 'Connection Closed'],
            ], 500)
            ->push(['status' => 'PENDING'], 201),
        '*/instance/fetchInstances*' => Http::response([
            [
                'name' => 'tido',
                'connectionStatus' => 'open',
                'ownerJid' => '60123456789@s.whatsapp.net',
                'number' => '60123456789',
            ],
        ]),
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/connect/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
    ]);

    $result = app(WhatsAppNotificationService::class)
        ->sendMessageResult('60123456789', 'hello');

    expect($result->ok)->toBeTrue();

    $sendTexts = Http::recorded(
        fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'),
    );
    $connects = Http::recorded(
        fn (Request $request): bool => str_contains($request->url(), '/instance/connect/tido')
            && $request->method() === 'GET'
            && ! str_contains($request->url(), 'number='),
    );

    expect($sendTexts)->toHaveCount(2)
        ->and($connects)->toHaveCount(1);
});

test('sendMessageResult does not reconnect when prisma session is not open', function () {
    config([
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
    ]);

    User::factory()->create(['phone' => '60123456789']);
    Cache::flush();

    Http::fake([
        '*/message/sendText/*' => Http::response([
            'status' => 500,
            'error' => 'Internal Server Error',
            'response' => ['message' => 'Connection Closed'],
        ], 500),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    $result = app(WhatsAppNotificationService::class)
        ->sendMessageResult('60123456789', 'hello');

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe(500);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/'));
});
