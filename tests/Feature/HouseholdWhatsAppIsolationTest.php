<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\EvolutionApiPage;
use App\Jobs\ProcessWhatsAppTextReplyJob;
use App\Models\EvolutionApiConnectionLog;
use App\Models\EvolutionApiSetting;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\EvolutionSettingsService;
use App\Services\HouseholdRegistrationService;
use App\Services\WhatsAppNotificationService;
use App\Support\CurrentHousehold;
use App\Support\EvolutionWebhookHousehold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const HH2_API_KEY = 'hh2-evolution-api-key-0123456789abcdef01234567';
const HH2_WEBHOOK_SECRET = 'hh2-evolution-webhook-secret-0123456789abcdef';
const HH2_PRIMARY_PHONE = '60199888777';
const HH2_FAMILY_PHONE = '60188777666';
const HH1_PRIMARY_PHONE = '60123456789';

beforeEach(function (): void {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_secret' => 'test-evolution-webhook-secret-0123456789abcdef0123456789abcdef',
        'services.evolution.webhook_allowed_ips' => '127.0.0.1,::1',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.allowed_api_hosts' => ['127.0.0.1', 'localhost', '::1', 'evolution.test'],
        'services.evolution.login_dev_otp' => null,
        'services.evolution.login_dev_phones' => '',
    ]);

    Cache::flush();
    RateLimiter::clear('whatsapp-webhook:ip:127.0.0.1');
    RateLimiter::clear('whatsapp-webhook:global');

    User::factory()->withWhatsAppPhone(HH1_PRIMARY_PHONE)->create();
});

afterEach(function (): void {
    CurrentHousehold::clear();
});

/**
 * @return array{primary: User, setting: EvolutionApiSetting}
 */
function registerConfiguredHouseholdTwo(bool $enabled = true): array
{
    $primary = app(HouseholdRegistrationService::class)->register([
        'name' => 'Second Primary',
        'email' => 'second-primary@example.com',
        'password' => 'password-password',
    ]);

    $primary->forceFill(['phone' => HH2_PRIMARY_PHONE])->save();

    $setting = app(EvolutionSettingsService::class)->save((int) $primary->household_id, [
        'api_url' => 'http://127.0.0.1:8080',
        'api_key' => HH2_API_KEY,
        'webhook_secret' => HH2_WEBHOOK_SECRET,
        'whatsapp_enabled' => $enabled,
    ]);

    return ['primary' => $primary->fresh(), 'setting' => $setting];
}

/**
 * @return array<string, mixed>
 */
function householdWhatsAppUpsertPayload(string $senderPhone, string $text, string $messageId, ?string $instanceName = null): array
{
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => $senderPhone.'@s.whatsapp.net',
                'fromMe' => false,
                'id' => $messageId,
            ],
            'messageType' => 'conversation',
            'message' => [
                'conversation' => $text,
            ],
        ],
    ];

    if ($instanceName !== null) {
        $payload['instance'] = $instanceName;
    }

    return $payload;
}

function requestUsesEvolutionApiKey(Request $request, string $apiKey): bool
{
    if ($request->hasHeader('apikey', $apiKey)) {
        return true;
    }

    foreach ($request->headers() as $name => $values) {
        if (strtolower((string) $name) !== 'apikey') {
            continue;
        }

        return in_array($apiKey, $values, true);
    }

    return false;
}

test('family member login users stamp the member household rather than household one', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo()['primary'];

    $member = FamilyMember::factory()->loginEnabled()->create([
        'household_id' => $householdTwo->household_id,
        'phone' => HH2_FAMILY_PHONE,
        'allowlist_enabled' => true,
    ]);

    $loginUser = User::query()
        ->withoutGlobalScope('household')
        ->where('family_member_id', $member->id)
        ->first();

    expect($loginUser)->not->toBeNull()
        ->and($loginUser?->household_id)->toBe($householdTwo->household_id)
        ->and($loginUser?->household_id)->not->toBe(1);
});

test('household two webhook secret routes spend jobs into household two', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();
    $householdId = (int) $householdTwo['primary']->household_id;
    $instanceName = (string) $householdTwo['setting']->instance_name;

    Queue::fake();
    Http::fake();

    $this->postJson(
        '/api/webhooks/whatsapp',
        householdWhatsAppUpsertPayload(HH2_PRIMARY_PHONE, 'spend', 'MSG-HH2-SPEND', $instanceName),
        evolutionWebhookHeaders(HH2_WEBHOOK_SECRET),
    )
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, function (ProcessWhatsAppTextReplyJob $job) use ($householdId): bool {
        return $job->senderNumber === HH2_PRIMARY_PHONE
            && $job->originalText === 'spend'
            && $job->householdId === $householdId;
    });
});

test('household two webhook ignores household one allowlisted senders', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();
    $instanceName = (string) $householdTwo['setting']->instance_name;

    Queue::fake();
    Http::fake();

    $this->postJson(
        '/api/webhooks/whatsapp',
        householdWhatsAppUpsertPayload(HH1_PRIMARY_PHONE, 'spend', 'MSG-HH2-STRANGER', $instanceName),
        evolutionWebhookHeaders(HH2_WEBHOOK_SECRET),
    )
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_sender']);

    Queue::assertNothingPushed();
});

test('environment webhook secret still routes to household one when household two exists', function (): void {
    registerConfiguredHouseholdTwo();

    Queue::fake();
    Http::fake();

    $this->postJson(
        '/api/webhooks/whatsapp',
        householdWhatsAppUpsertPayload(HH1_PRIMARY_PHONE, 'help', 'MSG-HH1-ENV'),
        evolutionWebhookHeaders(),
    )
        ->assertSuccessful()
        ->assertJson(['status' => 'accepted']);

    Queue::assertPushed(ProcessWhatsAppTextReplyJob::class, function (ProcessWhatsAppTextReplyJob $job): bool {
        return $job->senderNumber === HH1_PRIMARY_PHONE
            && $job->householdId === 1;
    });
});

test('environment webhook secret maps to household one while household two uses its own secret', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();

    $envSecret = (string) config('services.evolution.webhook_secret');

    expect(EvolutionWebhookHousehold::findSettingByWebhookSecret($envSecret)?->household_id)->toBe(1)
        ->and(EvolutionWebhookHousehold::findSettingByWebhookSecret(HH2_WEBHOOK_SECRET)?->household_id)
        ->toBe((int) $householdTwo['primary']->household_id);
});

test('household two cannot save the environment webhook secret', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();

    expect(fn () => app(EvolutionSettingsService::class)->save(
        (int) $householdTwo['primary']->household_id,
        [
            'api_url' => 'http://127.0.0.1:8080',
            'webhook_secret' => (string) config('services.evolution.webhook_secret'),
        ],
    ))->toThrow(ValidationException::class);
});

test('disabled household two webhook is ignored', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo(enabled: false);
    $instanceName = (string) $householdTwo['setting']->instance_name;

    Queue::fake();
    Http::fake();

    $this->postJson(
        '/api/webhooks/whatsapp',
        householdWhatsAppUpsertPayload(HH2_PRIMARY_PHONE, 'spend', 'MSG-HH2-DISABLED', $instanceName),
        evolutionWebhookHeaders(HH2_WEBHOOK_SECRET),
    )
        ->assertSuccessful()
        ->assertJson(['status' => 'ignored_disabled_household']);

    Queue::assertNothingPushed();
});

test('household two webhook rejects household one instance name', function (): void {
    registerConfiguredHouseholdTwo();

    $this->postJson(
        '/api/webhooks/whatsapp',
        householdWhatsAppUpsertPayload(HH2_PRIMARY_PHONE, 'spend', 'MSG-HH2-WRONG-INSTANCE', 'tido-hh-1'),
        evolutionWebhookHeaders(HH2_WEBHOOK_SECRET),
    )->assertUnprocessable();
});

test('household two spend replies exclude household one expenses', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();
    $householdId = (int) $householdTwo['primary']->household_id;

    Expense::factory()->create([
        'household_id' => 1,
        'total_amount' => 50.00,
        'date_time' => now(),
        'status' => 'reviewed',
    ]);
    Expense::factory()->create([
        'household_id' => $householdId,
        'total_amount' => 99.00,
        'date_time' => now(),
        'status' => 'reviewed',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    CurrentHousehold::set($householdId);

    $job = new ProcessWhatsAppTextReplyJob(HH2_PRIMARY_PHONE, 'spend', 'MSG-HH2-TOTALS', $householdId);
    $job->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(function (Request $request) use ($householdId): bool {
        $text = (string) $request['text'];

        return str_contains($request->url(), '/message/sendText/tido-hh-'.$householdId)
            && requestUsesEvolutionApiKey($request, HH2_API_KEY)
            && str_contains($text, '99.00')
            && ! str_contains($text, '50.00');
    });
});

test('otp for household two primary uses that household evolution instance', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();
    $householdId = (int) $householdTwo['primary']->household_id;

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    Livewire::test(Login::class)
        ->set('data.phone', HH2_PRIMARY_PHONE)
        ->call('sendOtp')
        ->assertHasNoErrors()
        ->assertSet('loginMode', 'otp');

    Http::assertSent(function (Request $request) use ($householdId): bool {
        return str_contains($request->url(), '/message/sendText/tido-hh-'.$householdId)
            && requestUsesEvolutionApiKey($request, HH2_API_KEY)
            && str_contains((string) $request['number'], HH2_PRIMARY_PHONE);
    });
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/tido-hh-1')
        && str_contains((string) $request['number'], HH2_PRIMARY_PHONE));
});

test('otp for household two family member uses that household evolution instance', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();
    $householdId = (int) $householdTwo['primary']->household_id;

    FamilyMember::factory()->loginEnabled()->create([
        'household_id' => $householdId,
        'phone' => HH2_FAMILY_PHONE,
        'allowlist_enabled' => true,
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    Livewire::test(Login::class)
        ->set('data.phone', HH2_FAMILY_PHONE)
        ->call('sendOtp')
        ->assertHasNoErrors()
        ->assertSet('loginMode', 'otp');

    Http::assertSent(function (Request $request) use ($householdId): bool {
        return str_contains($request->url(), '/message/sendText/tido-hh-'.$householdId)
            && requestUsesEvolutionApiKey($request, HH2_API_KEY)
            && str_contains((string) $request['number'], HH2_FAMILY_PHONE);
    });
});

test('otp for household one primary keeps using environment credentials', function (): void {
    registerConfiguredHouseholdTwo();

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    Livewire::test(Login::class)
        ->set('data.phone', HH1_PRIMARY_PHONE)
        ->call('sendOtp')
        ->assertHasNoErrors()
        ->assertSet('loginMode', 'otp');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/message/sendText/tido-hh-1')
            && requestUsesEvolutionApiKey($request, (string) config('services.evolution.api_key'))
            && str_contains((string) $request['number'], HH1_PRIMARY_PHONE);
    });
    Http::assertNotSent(fn (Request $request): bool => requestUsesEvolutionApiKey($request, HH2_API_KEY));
});

test('household two primary can enable WhatsApp and connect its own instance', function (): void {
    $householdTwo = app(HouseholdRegistrationService::class)->register([
        'name' => 'Second Primary',
        'email' => 'second-primary@example.com',
        'password' => 'password-password',
    ]);
    $householdTwo->forceFill(['phone' => HH2_PRIMARY_PHONE])->save();
    $householdId = (int) $householdTwo->household_id;

    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/connect/*' => Http::response([
            'base64' => 'HH2QR',
            'instance' => ['state' => 'connecting'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    $this->actingAs($householdTwo);
    CurrentHousehold::set($householdId);

    Livewire::test(EvolutionApiPage::class)
        ->callAction('configureSetup', data: [
            'api_url' => 'http://127.0.0.1:8080',
            'instance_name' => 'tido-hh-'.$householdId,
            'api_key' => HH2_API_KEY,
            'webhook_secret' => HH2_WEBHOOK_SECRET,
        ])
        ->call('generateQr')
        ->assertSet('qrBase64', 'data:image/png;base64,HH2QR');

    $setting = EvolutionApiSetting::query()
        ->withoutGlobalScopes()
        ->where('household_id', $householdId)
        ->first();

    expect($setting?->whatsapp_enabled)->toBeTrue()
        ->and($setting?->instance_name)->toBe('tido-hh-'.$householdId);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/tido-hh-'.$householdId)
        && requestUsesEvolutionApiKey($request, HH2_API_KEY));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/tido-hh-1'));
});

test('evolution connection logs stay inside the current household', function (): void {
    $householdTwo = registerConfiguredHouseholdTwo();
    $householdId = (int) $householdTwo['primary']->household_id;

    $householdOneLog = EvolutionApiConnectionLog::factory()->connected()->create([
        'household_id' => 1,
        'message' => 'HH1-ONLY-CONNECTION-LOG',
        'connected_number' => '60111111111',
    ]);
    $householdTwoLog = EvolutionApiConnectionLog::factory()->connected()->create([
        'household_id' => $householdId,
        'message' => 'HH2-ONLY-CONNECTION-LOG',
        'connected_number' => HH2_PRIMARY_PHONE,
    ]);

    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    $this->actingAs($householdTwo['primary']);
    CurrentHousehold::set($householdId);

    livewireDeferredTablePage(EvolutionApiPage::class)
        ->assertCanSeeTableRecords([$householdTwoLog])
        ->assertCanNotSeeTableRecords([$householdOneLog])
        ->assertDontSee('HH1-ONLY-CONNECTION-LOG')
        ->assertSee('HH2-ONLY-CONNECTION-LOG');
});
