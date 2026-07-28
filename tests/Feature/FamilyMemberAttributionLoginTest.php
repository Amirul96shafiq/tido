<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Support\DashboardMonthAnalytics;
use App\Filament\Support\DashboardMonthPeriod;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Models\User;
use App\Services\FamilyMemberLoginService;
use App\Services\WhatsAppLoginOtpService;
use App\Services\WhatsAppNotificationService;
use App\Support\DashboardSpenderScope;
use Database\Seeders\FamilyMemberLoginTestSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    config([
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.login_dev_otp' => '123456',
        'services.evolution.login_dev_phones' => FamilyMemberLoginTestSeeder::SAMPLE_PHONE,
    ]);
});

test('whatsapp media job attributes invoice to allowlisted family member', function () {
    Storage::fake('local');
    Queue::fake();

    $member = FamilyMember::factory()->create([
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => base64_encode('fake-receipt-binary-image'),
        ]),
    ]);

    (new ProcessWhatsAppMediaJob(
        '60111111111',
        '60111111111@s.whatsapp.net',
        'MSG-FAMILY',
        false,
    ))->handle(app(WhatsAppNotificationService::class));

    $invoice = Invoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->family_member_id)->toBe($member->id)
        ->and($invoice->whatsapp_sender)->toBe('60111111111');
});

test('whatsapp media job leaves family member null for primary sender', function () {
    Storage::fake('local');
    Queue::fake();

    User::factory()->withWhatsAppPhone('60123456789')->create();

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => base64_encode('fake-receipt-binary-image'),
        ]),
    ]);

    (new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-PRIMARY',
        false,
    ))->handle(app(WhatsAppNotificationService::class));

    expect(Invoice::query()->value('family_member_id'))->toBeNull();
});

test('dashboard analytics respects family member spender scope', function () {
    Invoice::unsetEventDispatcher();

    $month = now()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $month]);
    $member = FamilyMember::factory()->create();

    Invoice::create([
        'merchant_name' => 'Primary Store',
        'invoice_number' => 'INV-P',
        'receipt_hash' => 'hash-primary-scope',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 100.00,
        'total_tax' => 0.00,
        'total_amount' => 100.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
        'family_member_id' => null,
    ]);

    Invoice::create([
        'merchant_name' => 'Family Store',
        'invoice_number' => 'INV-F',
        'receipt_hash' => 'hash-family-scope',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 50.00,
        'total_tax' => 0.00,
        'total_amount' => 50.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'status' => 'reviewed',
        'family_member_id' => $member->id,
    ]);

    Invoice::setEventDispatcher(app('events'));

    $combined = new DashboardMonthAnalytics($bounds, new DashboardSpenderScope(DashboardSpenderScope::ALL));
    $primary = new DashboardMonthAnalytics($bounds, new DashboardSpenderScope(DashboardSpenderScope::PRIMARY));
    $family = new DashboardMonthAnalytics($bounds, new DashboardSpenderScope(DashboardSpenderScope::familyValue((int) $member->id)));

    expect($combined->summary()['current_total'])->toBe(150.0)
        ->and($primary->summary()['current_total'])->toBe(100.0)
        ->and($family->summary()['current_total'])->toBe(50.0);
});

test('login enabled family member gets linked user and can otp login with dev code', function () {
    Http::fake();

    $member = FamilyMember::factory()->create([
        'phone' => FamilyMemberLoginTestSeeder::SAMPLE_PHONE,
        'allowlist_enabled' => true,
        'login_enabled' => true,
    ]);

    app(FamilyMemberLoginService::class)->syncLoginUser($member);

    $user = User::query()->where('family_member_id', $member->id)->first();

    expect($user)->not->toBeNull()
        ->and($user->household_role)->toBe(HouseholdRole::FamilyMember)
        ->and($user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))->toBeTrue();

    Livewire::test(Login::class)
        ->set('data.phone', FamilyMemberLoginTestSeeder::SAMPLE_PHONE)
        ->call('sendOtp')
        ->assertHasNoErrors()
        ->assertSet('loginMode', 'otp')
        ->set('data.otp', '123456')
        ->call('authenticate')
        ->assertHasNoErrors();

    Http::assertNothingSent();

    expect(auth()->user()?->id)->toBe($user->id);
});

test('family member avatar syncs to linked login user profile photo', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => FamilyMemberLoginTestSeeder::SAMPLE_PHONE,
        'avatar_url' => null,
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    expect($user->avatar_url)->toBeNull();

    $member->update([
        'avatar_url' => 'avatars/family-member-synced.png',
    ]);

    expect($user->fresh()->avatar_url)->toBe('avatars/family-member-synced.png');
});

test('family member without login enabled cannot access panel', function () {
    $member = FamilyMember::factory()->notAllowlisted()->create([
        'phone' => '60119998888',
        'login_enabled' => false,
    ]);

    $user = User::factory()
        ->withWhatsAppPhone('60119998888')
        ->familyMember($member->id)
        ->create([
            'household_role' => HouseholdRole::FamilyMember,
        ]);

    expect($user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))->toBeFalse();
});

test('family member user cannot access evolution api page', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60118887777',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user)
        ->get(EvolutionApiPage::getUrl())
        ->assertForbidden();
});

test('family member user cannot access family members resource', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60117776666',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user)
        ->get(FamilyMemberResource::getUrl('index'))
        ->assertForbidden();
});

test('dev otp service stores fixed code without evolution send', function () {
    $user = User::factory()->withWhatsAppPhone(FamilyMemberLoginTestSeeder::SAMPLE_PHONE)->create();

    Http::fake();

    app(WhatsAppLoginOtpService::class)->send($user);

    Http::assertNothingSent();

    expect(app(WhatsAppLoginOtpService::class)->verify($user, '123456'))->toBeTrue();
});

test('family member login test seeder creates sample member and invoices', function () {
    $this->seed(FamilyMemberLoginTestSeeder::class);

    $member = FamilyMember::query()->where('phone', FamilyMemberLoginTestSeeder::SAMPLE_PHONE)->first();

    expect($member)->not->toBeNull()
        ->and($member->login_enabled)->toBeTrue()
        ->and(User::query()->where('family_member_id', $member->id)->exists())->toBeTrue()
        ->and(Invoice::query()->where('family_member_id', $member->id)->count())->toBe(2);
});

test('dashboard spender filter options use updated labels', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create([
        'name' => 'Amirul96Shafiq',
        'household_role' => HouseholdRole::Primary,
    ]);

    FamilyMember::factory()->create(['name' => 'Ahlong']);

    $options = DashboardSpenderScope::filterOptionsFor($user);

    expect($options[DashboardSpenderScope::ALL])->toBe('All')
        ->and($options[DashboardSpenderScope::PRIMARY])->toBe('Amirul96Shafiq');
});

test('family member spender options exclude primary and other members', function () {
    $memberA = FamilyMember::factory()->create(['name' => 'Alpha']);
    FamilyMember::factory()->create(['name' => 'Beta']);

    $user = User::factory()
        ->familyMember($memberA->id)
        ->create([
            'household_role' => HouseholdRole::FamilyMember,
            'phone' => '60115554444',
        ]);

    $options = DashboardSpenderScope::filterOptionsFor($user);

    expect($options)->toHaveKeys([DashboardSpenderScope::ALL, DashboardSpenderScope::familyValue((int) $memberA->id)])
        ->and($options)->not->toHaveKey(DashboardSpenderScope::PRIMARY)
        ->and($options)->not->toHaveKey(DashboardSpenderScope::familyValue((int) FamilyMember::query()->where('name', 'Beta')->value('id')));
});
