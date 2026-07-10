<?php

declare(strict_types=1);

use App\Filament\Pages\WhatsAppConnectionPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'app.url' => 'http://127.0.0.1:2000',
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('whatsapp connection page uses whatsapp-connection slug', function () {
    expect(WhatsAppConnectionPage::getSlug())->toBe('whatsapp-connection')
        ->and(WhatsAppConnectionPage::getNavigationLabel())->toBe('WhatsApp Connection')
        ->and(WhatsAppConnectionPage::getUrl())->toContain('/whatsapp-connection');
});

test('whatsapp connection page loads for authenticated user', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSuccessful()
        ->assertSet('connectionStatus', 'close')
        ->assertActionVisible('refreshStatus')
        ->assertActionVisible('generateQr')
        ->assertActionHidden('logoutSession')
        ->assertSee('Not connected');
});

test('connected status hides generate qr and shows logout', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'open')
        ->assertActionVisible('refreshStatus')
        ->assertActionHidden('generateQr')
        ->assertActionVisible('logoutSession')
        ->assertActionVisible('registerWebhook')
        ->assertActionVisible('sendPing');
});

test('generate qr prefers connect for a fresh code when instance exists', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/connect/*' => Http::response([
            'base64' => 'BBB',
            'instance' => ['state' => 'connecting'],
        ]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('generateQr')
        ->assertSet('qrBase64', 'data:image/png;base64,BBB')
        ->assertSet('connectionStatus', 'connecting')
        ->assertNotified();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/tido'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/create'));
});

test('generate qr creates baileys instance when connect fails', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/connect/*' => Http::response([
            'status' => 404,
            'error' => 'Not Found',
            'response' => ['message' => 'Instance does not exist'],
        ], 404),
        '*/instance/create' => Http::response([
            'instance' => [
                'instanceName' => 'tido',
                'status' => 'connecting',
            ],
            'qrcode' => [
                'base64' => 'data:image/png;base64,AAA',
            ],
        ]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('generateQr')
        ->assertSet('qrBase64', 'data:image/png;base64,AAA')
        ->assertSet('connectionStatus', 'connecting')
        ->assertNotified();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/instance/create')
            && $request['integration'] === 'WHATSAPP-BAILEYS'
            && $request['qrcode'] === true;
    });
});

test('logout session calls evolution logout endpoint', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/logout/*' => Http::response(['status' => 'success']),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('logoutSession')
        ->assertSet('qrBase64', null)
        ->assertNotified();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/logout/tido')
        && $request->method() === 'DELETE');
});

test('register webhook posts nested webhook payload', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('registerWebhook')
        ->assertNotified();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/webhook/set/tido')
            && data_get($request->data(), 'webhook.url') === 'http://127.0.0.1:2000/api/webhooks/whatsapp'
            && data_get($request->data(), 'webhook.events.0') === 'MESSAGES_UPSERT';
    });
});

test('send ping uses personal whatsapp number', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('sendPing')
        ->assertNotified();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['number'], '60123456789');
    });
});

test('does not auto-send welcome or webhook when page loads already connected', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    $user = auth()->user();

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'open')
        ->assertSet('welcomePingSent', false)
        ->assertSet('webhookRegistered', false);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/webhook/set/'));
    expect($user->notifications()->count())->toBe(0);
});

test('auto-registers webhook and sends welcome when status becomes open', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    $user = auth()->user();

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'connecting')
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true)
        ->assertNotified();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/webhook/set/tido')
            && data_get($request->data(), 'webhook.url') === 'http://127.0.0.1:2000/api/webhooks/whatsapp';
    });

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['number'], '60123456789')
            && str_contains((string) $request['text'], 'tido WhatsApp connected');
    });

    $user->refresh();
    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['title'])->toBe('WhatsApp connected')
        ->and($notification->data['actions'][0]['url'])->toBe(WhatsAppConnectionPage::getUrl());
});

test('welcome message and webhook are only sent once per connect session', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'open']]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true)
        ->call('refreshStatus')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true);

    expect(
        collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/message/sendText/'))
            ->count()
    )->toBe(1);

    expect(
        collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/webhook/set/'))
            ->count()
    )->toBe(1);
});

test('logout resets connect flags and stores disconnected database notification', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'close']]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
        '*/instance/logout/*' => Http::response(['status' => 'success']),
    ]);

    $user = auth()->user();

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true)
        ->call('logoutSession')
        ->assertSet('welcomePingSent', false)
        ->assertSet('webhookRegistered', false)
        ->assertNotified();

    $user->refresh();

    $titles = $user->notifications()->pluck('data')->map(fn (array $data): string => $data['title']);

    expect($titles)->toContain('WhatsApp connected')
        ->and($titles)->toContain('WhatsApp disconnected');

    $disconnected = $user->notifications()
        ->get()
        ->first(fn ($notification): bool => $notification->data['title'] === 'WhatsApp disconnected');

    expect($disconnected->data['actions'][0]['url'])->toBe(WhatsAppConnectionPage::getUrl());
});
