<?php

declare(strict_types=1);

use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
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
        && data_get($request->data(), 'numbers.0') === '60123456789');
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
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'PENDING'], 201),
    ]);

    $result = app(WhatsAppNotificationService::class)
        ->sendMessageResult('60123456789', 'hello');

    expect($result->ok)->toBeTrue()
        ->and($result->reason)->toBe('ok');
});
