<?php

declare(strict_types=1);

use App\Enums\EvolutionApiConnectMethod;
use App\Jobs\SendEvolutionApiConnectedAlertJob;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.instance_name' => 'tido',
    ]);

    User::factory()->create(['phone' => '60123456789']);
});

test('send evolution api connected alert job sends reconnect message to personal number', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    (new SendEvolutionApiConnectedAlertJob('601115666887'))->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), '/message/sendText/')) {
            return false;
        }

        $text = (string) data_get($request->data(), 'text', '');

        return str_contains((string) data_get($request->data(), 'number', ''), '60123456789')
            && str_contains($text, '*Connected*')
            && str_contains($text, 'reconnected')
            && str_contains($text, '601115666887')
            && str_contains($text, '— Powered by *tido*');
    });
});

test('send evolution api connected alert job mentions qr code connect method', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    (new SendEvolutionApiConnectedAlertJob('601115666887', EvolutionApiConnectMethod::QrCode))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) data_get($request->data(), 'text', '');

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, 'via QR code');
    });
});

test('send evolution api connected alert job mentions pairing code connect method', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    (new SendEvolutionApiConnectedAlertJob('601115666887', EvolutionApiConnectMethod::PairingCode))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) data_get($request->data(), 'text', '');

        return str_contains($request->url(), '/message/sendText/')
            && str_contains($text, 'via pairing code');
    });
});

test('send evolution api connected alert job skips when profile phone is missing', function () {
    User::query()->update(['phone' => null]);

    Http::fake();

    (new SendEvolutionApiConnectedAlertJob('601115666887'))->handle(app(WhatsAppNotificationService::class));

    Http::assertNothingSent();
});

test('send evolution api connected alert job retries when evolution send fails', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['error' => 'unavailable'], 503),
    ]);

    $job = new SendEvolutionApiConnectedAlertJob('601115666887');

    expect(fn () => $job->handle(app(WhatsAppNotificationService::class)))
        ->toThrow(RuntimeException::class);
});
