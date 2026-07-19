<?php

declare(strict_types=1);

use App\Support\WhatsAppPublicUrl;
use Illuminate\Support\Facades\URL;

test('base prefers configured whatsapp public app url', function () {
    config([
        'app.url' => 'http://localhost:2000',
        'services.evolution.public_app_url' => 'http://192.168.1.50:2000',
    ]);

    expect(WhatsAppPublicUrl::base())->toBe('http://192.168.1.50:2000');
});

test('base keeps non-loopback app url', function () {
    config([
        'app.url' => 'https://tido.example.test',
        'services.evolution.public_app_url' => null,
    ]);

    expect(WhatsAppPublicUrl::base())->toBe('https://tido.example.test');
});

test('withRoot forces generated absolute urls onto the whatsapp base', function () {
    config([
        'app.url' => 'http://localhost:2000',
        'services.evolution.public_app_url' => 'http://10.0.0.8:2000',
    ]);

    $url = WhatsAppPublicUrl::withRoot(fn (): string => URL::to('/admin/invoices/1/edit'));

    expect($url)->toStartWith('http://10.0.0.8:2000/')
        ->and($url)->toContain('/admin/invoices/1/edit');
});

test('isLoopbackHost recognizes localhost variants', function () {
    expect(WhatsAppPublicUrl::isLoopbackHost('localhost'))->toBeTrue()
        ->and(WhatsAppPublicUrl::isLoopbackHost('127.0.0.1'))->toBeTrue()
        ->and(WhatsAppPublicUrl::isLoopbackHost('tido.local'))->toBeFalse();
});
