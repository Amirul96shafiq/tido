<?php

declare(strict_types=1);

use App\Enums\WhatsAppConnectionEvent;
use App\Filament\Pages\WhatsAppConnectionPage;
use App\Models\User;
use App\Models\WhatsAppConnectionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeConnectedInstance(array $overrides = []): array
{
    return array_merge([
        'id' => 'instance-uuid-tido',
        'name' => 'tido',
        'connectionStatus' => 'open',
        'ownerJid' => '601115666887@s.whatsapp.net',
        'profileName' => 'tido Bot',
        'integration' => 'WHATSAPP-BAILEYS',
        'number' => null,
        'updatedAt' => '2026-07-10T07:46:09.433Z',
        '_count' => [
            'Message' => 12,
            'Contact' => 4,
            'Chat' => 3,
        ],
    ], $overrides);
}

beforeEach(function () {
    config([
        'app.url' => 'http://127.0.0.1:2000',
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.personal_number' => '601116330705',
        'services.evolution.personal_extra_numbers' => '60111111111',
        'services.evolution.device_label' => 'tido App (Evolution API)',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('601116330705')->create());
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
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSuccessful()
        ->assertSet('connectionStatus', 'close')
        ->assertActionVisible('refreshStatus')
        ->assertActionVisible('generateQr')
        ->assertActionHidden('logoutSession')
        ->assertSee('Not connected')
        ->assertSee('Connection history')
        ->assertSee('No connection events yet');
});

test('connected status shows linked number and instance details', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'open')
        ->assertSet('connectedNumber', '601115666887')
        ->assertSet('connectedProfileName', 'tido Bot')
        ->assertSet('connectedInstanceId', 'instance-uuid-tido')
        ->assertSet('connectedIntegration', 'WHATSAPP-BAILEYS')
        ->assertSet('connectedMessageCount', 12)
        ->assertSee('Connected number')
        ->assertSee('601115666887')
        ->assertSee('tido Bot')
        ->assertSee('Bot allowlist')
        ->assertSee('601116330705')
        ->assertSee('60111111111')
        ->assertSee('View details')
        ->assertSee('Connection details')
        ->assertSee('tido App (Evolution API)')
        ->assertSee('instance-uuid-tido')
        ->assertActionVisible('refreshStatus')
        ->assertActionHidden('generateQr')
        ->assertActionVisible('logoutSession')
        ->assertActionVisible('registerWebhook')
        ->assertActionVisible('sendPing');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/fetchInstances'));
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
        '*/instance/fetchInstances*' => Http::response([]),
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
        '*/instance/fetchInstances*' => Http::response([]),
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
        '*/instance/fetchInstances*' => Http::response([]),
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
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
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
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('sendPing')
        ->assertNotified();

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), '/message/sendText/')) {
            return false;
        }

        $text = (string) data_get($request->data(), 'text', '');

        return str_contains((string) data_get($request->data(), 'number', ''), '601116330705')
            && str_contains($text, '*Test ping*')
            && str_contains($text, '— Powered by *tido*')
            && str_contains($text, "\n\n")
            && ! str_contains($text, '\n');
    });
});

test('does not auto-send welcome or webhook when page loads already connected', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    $user = auth()->user();

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'open')
        ->assertSet('connectedNumber', '601115666887')
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
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    $user = auth()->user();

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'connecting')
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->assertSet('connectedNumber', '601115666887')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true)
        ->assertNotified();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/webhook/set/tido')
            && data_get($request->data(), 'webhook.url') === 'http://127.0.0.1:2000/api/webhooks/whatsapp';
    });

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), '/message/sendText/')) {
            return false;
        }

        $text = (string) data_get($request->data(), 'text', '');

        return str_contains((string) data_get($request->data(), 'number', ''), '601116330705')
            && str_contains($text, '*Connected*')
            && str_contains($text, '— Powered by *tido*')
            && str_contains($text, "\n\n")
            && ! str_contains($text, '\n');
    });

    $user->refresh();
    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['title'])->toBe('WhatsApp connected')
        ->and($notification->data['actions'][0]['url'])->toBe(WhatsAppConnectionPage::getUrl());

    expect(WhatsAppConnectionLog::query()->count())->toBe(1);

    $log = WhatsAppConnectionLog::query()->first();
    expect($log->event)->toBe(WhatsAppConnectionEvent::Connected)
        ->and($log->connected_number)->toBe('601115666887')
        ->and($log->profile_name)->toBe('tido Bot')
        ->and($log->status)->toBe('open');
});

test('welcome message and webhook are only sent once per connect session', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'open']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
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

    expect(WhatsAppConnectionLog::query()->where('event', WhatsAppConnectionEvent::Connected)->count())->toBe(1);
});

test('refresh status logs disconnected when session closes', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'close']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'close')
        ->assertSee('Disconnected');

    expect(WhatsAppConnectionLog::query()->latest('id')->pluck('event')->all())
        ->toBe([
            WhatsAppConnectionEvent::Disconnected,
            WhatsAppConnectionEvent::Connected,
        ]);

    $disconnected = WhatsAppConnectionLog::query()
        ->where('event', WhatsAppConnectionEvent::Disconnected)
        ->first();

    expect($disconnected->connected_number)->toBe('601115666887')
        ->and($disconnected->status)->toBe('close');
});

test('logout resets connect flags and stores disconnected database notification', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'close']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/message/sendText/*' => Http::response(['status' => 'success']),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
        '*/instance/logout/*' => Http::response(['status' => 'success']),
    ]);

    $user = auth()->user();

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true)
        ->assertSet('connectedNumber', '601115666887')
        ->call('logoutSession')
        ->assertSet('welcomePingSent', false)
        ->assertSet('webhookRegistered', false)
        ->assertSet('connectedNumber', null)
        ->assertNotified();

    $user->refresh();

    $titles = $user->notifications()->pluck('data')->map(fn (array $data): string => $data['title']);

    expect($titles)->toContain('WhatsApp connected')
        ->and($titles)->toContain('WhatsApp disconnected');

    $disconnected = $user->notifications()
        ->get()
        ->first(fn ($notification): bool => $notification->data['title'] === 'WhatsApp disconnected');

    expect($disconnected->data['actions'][0]['url'])->toBe(WhatsAppConnectionPage::getUrl());

    expect(WhatsAppConnectionLog::query()->latest('id')->pluck('event')->all())
        ->toBe([
            WhatsAppConnectionEvent::Logout,
            WhatsAppConnectionEvent::Connected,
        ]);

    $logout = WhatsAppConnectionLog::query()
        ->where('event', WhatsAppConnectionEvent::Logout)
        ->first();

    expect($logout->connected_number)->toBe('601115666887')
        ->and($logout->meta['source'] ?? null)->toBe('logout');
});

test('connection history section lists previous logs', function () {
    $connected = WhatsAppConnectionLog::factory()->connected()->create([
        'connected_number' => '601115666887',
        'message' => 'WhatsApp session connected (601115666887).',
    ]);
    $logout = WhatsAppConnectionLog::factory()->logout()->create([
        'connected_number' => '601115666887',
        'message' => 'WhatsApp session logged out (601115666887).',
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSee('Connection history')
        ->assertCanSeeTableRecords([$connected, $logout])
        ->searchTable('logged out')
        ->assertCanSeeTableRecords([$logout])
        ->assertCanNotSeeTableRecords([$connected])
        ->searchTable(null)
        ->filterTable('event', WhatsAppConnectionEvent::Connected->value)
        ->assertCanSeeTableRecords([$connected])
        ->assertCanNotSeeTableRecords([$logout]);
});
