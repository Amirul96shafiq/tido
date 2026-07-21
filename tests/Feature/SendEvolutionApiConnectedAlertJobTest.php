<?php

declare(strict_types=1);

use App\Enums\WhatsAppConnectMethod;
use App\Jobs\SendWhatsAppConnectedAlertJob;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.personal_number' => '60123456789',
    ]);
});

test('send whatsapp connected alert job sends reconnect message to personal number', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    (new SendWhatsAppConnectedAlertJob('601115666887'))->handle(app(WhatsAppNotificationService::class));

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

test('send whatsapp connected alert job mentions qr code connect method', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    (new SendWhatsAppConnectedAlertJob('601115666887', WhatsAppConnectMethod::QrCode))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) data_get($request->data(), 'text', '');

        return str_contains($text, 'via QR code');
    });
});

test('send whatsapp connected alert job mentions pairing code connect method', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    (new SendWhatsAppConnectedAlertJob('601115666887', WhatsAppConnectMethod::PairingCode))
        ->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) {
        $text = (string) data_get($request->data(), 'text', '');

        return str_contains($text, 'via pairing code');
    });
});

test('send whatsapp connected alert job skips when personal number is missing', function () {
    config(['services.evolution.personal_number' => null]);

    Http::fake();

    (new SendWhatsAppConnectedAlertJob('601115666887'))->handle(app(WhatsAppNotificationService::class));

    Http::assertNothingSent();
});

test('send whatsapp connected alert job retries when evolution send fails', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['error' => 'unavailable'], 503),
    ]);

    $job = new SendWhatsAppConnectedAlertJob('601115666887');

    expect(fn () => $job->handle(app(WhatsAppNotificationService::class)))
        ->toThrow(RuntimeException::class);
});
