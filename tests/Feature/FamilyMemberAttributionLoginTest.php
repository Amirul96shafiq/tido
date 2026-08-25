<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Support\DashboardMonthAnalytics;
use App\Filament\Support\DashboardMonthPeriod;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyMemberLoginService;
use App\Services\WhatsAppLoginOtpService;
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
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.login_dev_otp' => '123456',
        'services.evolution.login_dev_phones' => FamilyMemberLoginTestSeeder::SAMPLE_PHONE,
    ]);
});

test('whatsapp media job attributes expense to allowlisted family member', function () {
    Storage::fake('local');
    Queue::fake();

    $member = FamilyMember::factory()->create([
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60111111111',
        '60111111111@s.whatsapp.net',
        'MSG-FAMILY',
        false,
    );
    app()->call([$job, 'handle']);

    $expense = Expense::query()->first();

    expect($expense)->not->toBeNull()
        ->and($expense->family_member_id)->toBe($member->id)
        ->and($expense->whatsapp_sender)->toBe('60111111111');
});

test('whatsapp media job leaves family member null for primary sender', function () {
    Storage::fake('local');
    Queue::fake();

    User::factory()->withWhatsAppPhone('60123456789')->create();

    Http::fake([
        '*/chat/getBase64FromMediaMessage/*' => Http::response([
            'base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]),
    ]);

    $job = new ProcessWhatsAppMediaJob(
        '60123456789',
        '60123456789@s.whatsapp.net',
        'MSG-PRIMARY',
        false,
    );
    app()->call([$job, 'handle']);

    expect(Expense::query()->value('family_member_id'))->toBeNull();
});

test('dashboard analytics respects family member spender scope', function () {
    Expense::unsetEventDispatcher();

    $month = now()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $month]);
    $member = FamilyMember::factory()->create();

    Expense::create([
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

    Expense::create([
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

    Expense::setEventDispatcher(app('events'));

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

test('family member profile edit syncs shared fields back to family member resource', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => FamilyMemberLoginTestSeeder::SAMPLE_PHONE,
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'Ahlong',
        'date_of_birth' => '1988-11-11',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();
    $user->update(['notify_profile_updates' => false]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.display_name', 'Alongg')
        ->set('data.name', 'Nor Ezrieana Updated')
        ->set('data.date_of_birth', '1990-12-12')
        ->call('save')
        ->assertHasNoErrors();

    $member->refresh();

    expect($member->display_name)->toBe('Alongg')
        ->and($member->name)->toBe('Nor Ezrieana Updated')
        ->and($member->date_of_birth?->format('Y-m-d'))->toBe('1990-12-12')
        ->and($member->phone)->toBe(FamilyMemberLoginTestSeeder::SAMPLE_PHONE);
});

test('family member date of birth syncs to linked login user with synthetic email', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => FamilyMemberLoginTestSeeder::SAMPLE_PHONE,
        'date_of_birth' => null,
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    expect($user->email)->toBe('family+'.$member->id.'@tido.local')
        ->and($user->date_of_birth)->toBeNull();

    $member->update([
        'date_of_birth' => '1990-05-15',
    ]);

    $user->refresh();

    expect($user->email)->toBe('family+'.$member->id.'@tido.local')
        ->and($user->date_of_birth?->format('Y-m-d'))->toBe('1990-05-15');
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
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('family member user cannot access family members resource', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60117776666',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user)
        ->get(FamilyMemberResource::getUrl('index'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('dev otp service stores fixed code without evolution send', function () {
    $user = User::factory()->withWhatsAppPhone(FamilyMemberLoginTestSeeder::SAMPLE_PHONE)->create();

    Http::fake();

    app(WhatsAppLoginOtpService::class)->send($user);

    Http::assertNothingSent();

    expect(app(WhatsAppLoginOtpService::class)->verify($user, '123456'))->toBeTrue();
});

test('family member login test seeder creates sample member and expenses', function () {
    $this->seed(FamilyMemberLoginTestSeeder::class);

    $member = FamilyMember::query()->where('phone', FamilyMemberLoginTestSeeder::SAMPLE_PHONE)->first();

    expect($member)->not->toBeNull()
        ->and($member->login_enabled)->toBeTrue()
        ->and(User::query()->where('family_member_id', $member->id)->exists())->toBeTrue()
        ->and(Expense::query()->where('family_member_id', $member->id)->count())->toBe(2);
});

test('dashboard spender filter options use updated labels', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create([
        'name' => 'Amirul96Shafiq',
        'display_name' => null,
        'household_role' => HouseholdRole::Primary,
    ]);

    FamilyMember::factory()->create(['name' => 'Ahlong', 'display_name' => null]);

    $options = DashboardSpenderScope::filterOptionsFor($user);

    expect($options[DashboardSpenderScope::ALL])->toBe('All')
        ->and($options[DashboardSpenderScope::PRIMARY])->toBe('Amirul96Shafiq (me)');
});

test('dashboard spender filter options use primary user display_name if filled', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create([
        'name' => 'Amirul Shafiq Harun',
        'display_name' => 'Amirul96Shafiq',
        'household_role' => HouseholdRole::Primary,
    ]);

    $options = DashboardSpenderScope::filterOptionsFor($user);

    expect($options[DashboardSpenderScope::PRIMARY])->toBe('Amirul96Shafiq (me)');
});

test('family member spender options exclude primary and other members', function () {
    $memberA = FamilyMember::factory()->create(['name' => 'Alpha', 'display_name' => null]);
    FamilyMember::factory()->create(['name' => 'Beta', 'display_name' => null]);

    $user = User::factory()
        ->familyMember($memberA->id)
        ->create([
            'household_role' => HouseholdRole::FamilyMember,
            'phone' => '60115554444',
        ]);

    $options = DashboardSpenderScope::filterOptionsFor($user);
    $selfKey = DashboardSpenderScope::familyValue((int) $memberA->id);

    expect($options)->toHaveKeys([DashboardSpenderScope::ALL, $selfKey])
        ->and($options[$selfKey])->toBe('Alpha (me)')
        ->and($options)->not->toHaveKey(DashboardSpenderScope::PRIMARY)
        ->and($options)->not->toHaveKey(DashboardSpenderScope::familyValue((int) FamilyMember::query()->where('name', 'Beta')->value('id')));
});

test('spender scope defaults to the current user', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $member = FamilyMember::factory()->create(['name' => 'Alpha']);
    $familyUser = User::factory()
        ->familyMember($member->id)
        ->create([
            'household_role' => HouseholdRole::FamilyMember,
            'phone' => '60115554444',
        ]);

    expect(DashboardSpenderScope::defaultFor($primary)->value())->toBe(DashboardSpenderScope::PRIMARY)
        ->and(DashboardSpenderScope::defaultFor($familyUser)->value())->toBe(DashboardSpenderScope::familyValue((int) $member->id));

    $this->actingAs($primary);

    expect(DashboardSpenderScope::fromFilters([])->value())->toBe(DashboardSpenderScope::PRIMARY);

    $this->actingAs($familyUser);

    expect(DashboardSpenderScope::fromFilters([])->value())->toBe(DashboardSpenderScope::familyValue((int) $member->id));
});
