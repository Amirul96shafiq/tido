<?php

declare(strict_types=1);

use App\Enums\WhatsAppConnectionEvent;
use App\Enums\WhatsAppConnectMethod;
use App\Filament\Pages\WhatsAppConnectionPage;
use App\Jobs\SendWhatsAppConnectedAlertJob;
use App\Models\User;
use App\Models\WhatsAppConnectionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        ->assertActionVisible('pairWithCode')
        ->assertActionEnabled('generateQr')
        ->assertActionEnabled('pairWithCode')
        ->assertActionDisabled('logoutSession')
        ->assertActionDisabled('registerWebhook')
        ->assertActionDisabled('sendPing')
        ->assertSee('Not connected')
        ->assertSee('Connect')
        ->assertSee('Connection history')
        ->assertSee('No connection events yet');
});

test('connected status shows linked number and instance details', function () {
    WhatsAppConnectionLog::factory()->connected()->create([
        'connected_number' => '601115666887',
        'meta' => [
            'source' => 'page',
            'connect_method' => 'pairing_code',
        ],
    ]);

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
        ->assertSet('connectedVia', WhatsAppConnectMethod::PairingCode)
        ->assertSet('connectedMessageCount', 12)
        ->assertSee('Connected number')
        ->assertSee('601115666887')
        ->assertSee('tido Bot')
        ->assertSee('Connected via')
        ->assertSee('pairing code')
        ->assertSee('Contact allowlist')
        ->assertSee('601116330705')
        ->assertSee('60111111111')
        ->assertSee('View details')
        ->assertSee('Connection details')
        ->assertSee('Google Chrome (Mac OS)')
        ->assertDontSee('tido App (Evolution API)')
        ->assertSee('instance-uuid-tido')
        ->assertActionVisible('refreshStatus')
        ->assertActionVisible('connect')
        ->assertActionDisabled('connect')
        ->assertActionEnabled('logoutSession')
        ->assertActionEnabled('registerWebhook')
        ->assertActionEnabled('sendPing');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/fetchInstances'));
});

test('connected via qr code shows configured device label in details', function () {
    WhatsAppConnectionLog::factory()->connected()->create([
        'connected_number' => '601115666887',
        'meta' => [
            'source' => 'page',
            'connect_method' => 'qr_code',
        ],
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectedVia', WhatsAppConnectMethod::QrCode)
        ->assertSee('tido App (Evolution API)')
        ->assertDontSee('Google Chrome (Mac OS)');
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

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/tido')
        && ! str_contains($request->url(), 'number='));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/create'));
});

test('connect ttl matches evolution baileys qrTimeout of forty-five seconds', function () {
    expect(WhatsAppConnectionPage::CONNECT_TTL_SECONDS)->toBe(45);
});

test('pair with code modal uses compact width and blurred overlay hook', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.fi-modal-close-overlay.fi-modal-overlay-blur')
        ->and(file_exists(base_path('docs/ui-modal-overlay.md')))->toBeTrue();
});

test('pair with code requests evolution connect with submitted number', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/connect/*' => Http::response([
            'pairingCode' => 'ABCD1234',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => '601115666887',
        ])
        ->assertSet('pairingCode', 'ABCD1234')
        ->assertSet('pairingNumber', '601115666887')
        ->assertSet('qrBase64', null)
        ->assertSet('connectionStatus', 'connecting')
        ->assertSee('ABCD-1234')
        ->assertSee('Copy code')
        ->assertNotified();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/tido')
        && str_contains($request->url(), 'number=601115666887'));
});

test('pair with code validates malaysian phone number', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => 'invalid',
        ])
        ->assertHasActionErrors(['number'])
        ->assertSet('pairingCode', null);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/'));
});

test('refresh status does not call connect while a pairing code is active', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/logout/*' => Http::response(['status' => 'success']),
        '*/instance/connect/*' => Http::response([
            'pairingCode' => 'HWATQDD2',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    $component = Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => '601115666887',
        ])
        ->assertSet('pairingCode', 'HWATQDD2');

    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/connect/*' => Http::response([
            'pairingCode' => 'DC699MH8',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    $component
        ->call('refreshStatus')
        ->assertSet('pairingCode', 'HWATQDD2');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/'));
});

test('keeps polling while connecting even without a qr or pairing code', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    $component = Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'connecting')
        ->assertSet('qrBase64', null)
        ->assertSet('pairingCode', null);

    expect($component->instance()->getPollingInterval())->toBe('5s');
});

test('does not call connect sync while evolution reports close during restart', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'close']])
            ->push(['instance' => ['state' => 'close']]),
        '*/instance/connect/*' => Http::response([
            'base64' => 'fresh-qr',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->set('qrBase64', 'data:image/png;base64,old')
        ->set('qrGeneratedAt', time())
        ->set('connectionStatus', 'close')
        ->call('refreshStatus')
        ->assertSet('qrBase64', 'data:image/png;base64,old');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/'));
});

test('cancel connecting logs out evolution and clears pairing display', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'close']])
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'close']]),
        '*/instance/connect/*' => Http::response([
            'pairingCode' => 'QM1MP43P',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/logout/*' => Http::response(['status' => 'success']),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => '601115666887',
        ])
        ->assertSet('pairingCode', 'QM1MP43P')
        ->assertSet('connectionStatus', 'connecting')
        ->assertActionVisible('cancelConnecting')
        ->callAction('cancelConnecting')
        ->assertSet('pairingCode', null)
        ->assertSet('pairingNumber', null)
        ->assertSet('qrBase64', null)
        ->assertSet('pendingConnectMethod', null)
        ->assertSet('connectionStatus', 'close')
        ->assertNotified();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/logout/tido')
        && $request->method() === 'DELETE');
});

test('cancel connecting is hidden when disconnected without an active attempt', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->assertSet('connectionStatus', 'close')
        ->assertActionHidden('cancelConnecting');
});

test('pair with code polls connect when evolution is connecting without a code yet', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/logout/*' => Http::response(['status' => 'success']),
        '*/instance/connect/*' => Http::sequence()
            ->push([
                'base64' => 'BBB',
                'instance' => ['state' => 'connecting'],
            ])
            ->push([
                'pairingCode' => 'WXYZ5678',
                'instance' => ['state' => 'connecting'],
            ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => '601115666887',
        ])
        ->assertSet('pairingCode', 'WXYZ5678')
        ->assertSet('pairingNumber', '601115666887')
        ->assertNotified();

    // One logout to clear stale creds before pairing — not a mid-flight retry.
    expect(
        collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/instance/logout/tido')
                && $pair[0]->method() === 'DELETE')
            ->count()
    )->toBe(1);

    expect(
        collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/instance/connect/')
                && str_contains($pair[0]->url(), 'number=601115666887'))
            ->count()
    )->toBeGreaterThanOrEqual(2);
});

test('copy pairing code action notifies success', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/connect/*' => Http::response([
            'pairingCode' => 'ABCD-1234',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => '601115666887',
        ])
        ->call('copyPairingCode')
        ->assertNotified();
});

test('generate qr clears pairing code display', function () {
    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/connect/*' => Http::sequence()
            ->push([
                'pairingCode' => 'ABCD1234',
                'instance' => ['state' => 'connecting'],
            ])
            ->push([
                'base64' => 'BBB',
                'instance' => ['state' => 'connecting'],
            ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => '601115666887',
        ])
        ->assertSet('pairingCode', 'ABCD1234')
        ->call('generateQr')
        ->assertSet('pairingCode', null)
        ->assertSet('qrBase64', 'data:image/png;base64,BBB');
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
        ->assertSet('webhookRegistered', true)
        ->assertActionDisabled('registerWebhook')
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

test('auto-registers webhook and queues welcome when status becomes open', function () {
    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
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
        ->assertActionDisabled('registerWebhook')
        ->assertNotified();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/webhook/set/tido')
            && data_get($request->data(), 'webhook.url') === 'http://127.0.0.1:2000/api/webhooks/whatsapp';
    });

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));

    Queue::assertPushed(SendWhatsAppConnectedAlertJob::class, function (SendWhatsAppConnectedAlertJob $job): bool {
        return $job->connectedNumber === '601115666887'
            && $job->connectMethod === null;
    });

    $user->refresh();
    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['title'])->toBe('WhatsApp connected')
        ->and($notification->data['actions'][0]['url'])->toBe(WhatsAppConnectionPage::getUrl())
        ->and($notification->data['actions'][0]['shouldOpenUrlInNewTab'])->toBeTrue();

    expect(WhatsAppConnectionLog::query()->count())->toBe(1);

    $log = WhatsAppConnectionLog::query()->first();
    expect($log->event)->toBe(WhatsAppConnectionEvent::Connected)
        ->and($log->connected_number)->toBe('601115666887')
        ->and($log->profile_name)->toBe('tido Bot')
        ->and($log->status)->toBe('open')
        ->and($log->meta['connect_method'] ?? null)->toBeNull();
});

test('auto-registers webhook and queues welcome with qr connect method after generate qr', function () {
    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'close']])
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']]),
        '*/instance/connect/*' => Http::response([
            'base64' => 'BBB',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('generateQr')
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'connecting')
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->assertSet('welcomePingSent', true);

    Queue::assertPushed(SendWhatsAppConnectedAlertJob::class, function (SendWhatsAppConnectedAlertJob $job): bool {
        return $job->connectMethod === WhatsAppConnectMethod::QrCode;
    });

    $log = WhatsAppConnectionLog::query()->latest('id')->first();
    expect($log->meta['connect_method'] ?? null)->toBe('qr_code');
});

test('auto-registers webhook and queues welcome with pairing code connect method', function () {
    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'close']])
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']]),
        '*/instance/connect/*' => Http::response([
            'pairingCode' => 'ABCD1234',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->callAction('pairWithCode', data: [
            'number' => '601115666887',
        ])
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'connecting')
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->assertSet('welcomePingSent', true);

    Queue::assertPushed(SendWhatsAppConnectedAlertJob::class, function (SendWhatsAppConnectedAlertJob $job): bool {
        return $job->connectMethod === WhatsAppConnectMethod::PairingCode;
    });

    $log = WhatsAppConnectionLog::query()->latest('id')->first();
    expect($log->meta['connect_method'] ?? null)->toBe('pairing_code');
});

test('skips whatsapp connection database notifications when preference is disabled', function () {
    auth()->user()->update(['notify_whatsapp_connection' => false]);

    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'close']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
        '*/instance/logout/*' => Http::response(['status' => 'success']),
    ]);

    $user = auth()->user();

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->assertSet('welcomePingSent', true)
        ->call('logoutSession')
        ->assertSet('connectionStatus', 'close');

    $user->refresh();

    expect($user->notifications()->count())->toBe(0);
    expect(WhatsAppConnectionLog::query()->count())->toBe(2);
});

test('welcome message and webhook are only sent once per connect session', function () {
    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'open']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true)
        ->call('refreshStatus')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true);

    Queue::assertPushed(SendWhatsAppConnectedAlertJob::class, 1);

    expect(
        collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/webhook/set/'))
            ->count()
    )->toBe(1);

    expect(WhatsAppConnectionLog::query()->where('event', WhatsAppConnectionEvent::Connected)->count())->toBe(1);
});

test('refresh status logs disconnected when session closes', function () {
    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'close']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'close')
        ->assertSet('welcomePingSent', false)
        ->assertSet('webhookRegistered', false)
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

test('reconnect after disconnect dispatches welcome alert again', function () {
    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'close']])
            ->push(['instance' => ['state' => 'open']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
        '*/webhook/set/*' => Http::response(['status' => 'success'], 201),
    ]);

    Livewire::test(WhatsAppConnectionPage::class)
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true)
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'close')
        ->assertSet('welcomePingSent', false)
        ->assertSet('webhookRegistered', false)
        ->call('refreshStatus')
        ->assertSet('connectionStatus', 'open')
        ->assertSet('welcomePingSent', true)
        ->assertSet('webhookRegistered', true);

    Queue::assertPushed(SendWhatsAppConnectedAlertJob::class, 2);

    expect(WhatsAppConnectionLog::query()->where('event', WhatsAppConnectionEvent::Connected)->count())->toBe(2)
        ->and(WhatsAppConnectionLog::query()->where('event', WhatsAppConnectionEvent::Disconnected)->count())->toBe(1);
});

test('logout resets connect flags and stores disconnected database notification', function () {
    Queue::fake([
        SendWhatsAppConnectedAlertJob::class,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::sequence()
            ->push(['instance' => ['state' => 'connecting']])
            ->push(['instance' => ['state' => 'open']])
            ->push(['instance' => ['state' => 'close']]),
        '*/instance/fetchInstances*' => Http::response([fakeConnectedInstance()]),
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

    expect($disconnected->data['actions'][0]['url'])->toBe(WhatsAppConnectionPage::getUrl())
        ->and($disconnected->data['actions'][0]['shouldOpenUrlInNewTab'])->toBeTrue();

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
    $when = now()->subHours(4);
    $relative = $when->diffForHumans();

    $connected = WhatsAppConnectionLog::factory()->connected()->create([
        'connected_number' => '601115666887',
        'message' => 'WhatsApp session connected (601115666887).',
        'created_at' => $when,
        'meta' => [
            'source' => 'page',
            'connect_method' => 'qr_code',
        ],
    ]);
    $connectedViaPairing = WhatsAppConnectionLog::factory()->connected()->create([
        'connected_number' => '601115666888',
        'message' => 'WhatsApp session connected (601115666888).',
        'meta' => [
            'source' => 'page',
            'connect_method' => 'pairing_code',
        ],
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

    $component = Livewire::test(WhatsAppConnectionPage::class)
        ->assertSee('Connection history')
        ->assertSee('Connected via')
        ->assertSee('QR code')
        ->assertSee('pairing code')
        ->assertSee($relative)
        ->assertCanSeeTableRecords([$connected, $connectedViaPairing, $logout])
        ->searchTable('logged out')
        ->assertCanSeeTableRecords([$logout])
        ->assertCanNotSeeTableRecords([$connected])
        ->searchTable(null)
        ->filterTable('event', WhatsAppConnectionEvent::Connected->value)
        ->assertCanSeeTableRecords([$connected])
        ->assertCanNotSeeTableRecords([$logout]);

    $column = $component->instance()->getTable()->getColumn('created_at');

    expect($column)->not->toBeNull();

    $tooltip = $column->record($connected)->getTooltip($when);

    expect($tooltip)->toBeString()->not->toBeEmpty()
        ->and($tooltip)->not->toBe($relative);
});
