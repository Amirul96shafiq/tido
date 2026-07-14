<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.instance_name' => 'tido',
        'services.evolution.personal_number' => '60123456789',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);
});

test('guest can open filament login page', function () {
    $this->get('/admin/login')->assertSuccessful();
});

test('send otp advances to otp step and posts to evolution', function () {
    User::factory()->withWhatsAppPhone('60123456789')->create([
        'email' => 'admin@tido.local',
    ]);

    Livewire::test(Login::class)
        ->assertSet('loginMode', 'phone')
        ->set('data.phone', '0123456789')
        ->call('sendOtp')
        ->assertHasNoErrors()
        ->assertSet('loginMode', 'otp')
        ->assertSet('pendingPhone', '60123456789');

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['number'], '60123456789');
    });
});

test('otp step renders six digit input boxes', function () {
    User::factory()->withWhatsAppPhone('60123456789')->create();

    $html = Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp')
        ->html();

    expect($html)
        ->toContain('fi-one-time-code-input-ctn')
        ->and(substr_count($html, 'fi-one-time-code-input-digit-field'))->toBe(6)
        ->and($html)->not->toContain('placeholder="6-digit code"');
});

test('send otp fails for unknown phone without revealing details', function () {
    Livewire::test(Login::class)
        ->set('data.phone', '0199999999')
        ->call('sendOtp')
        ->assertHasErrors(['data.phone'])
        ->assertSet('loginMode', 'phone');

    Http::assertNothingSent();
});

test('otp login authenticates with correct code after phone step', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create([
        'email' => 'admin@tido.local',
    ]);

    $component = Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp')
        ->assertSet('pendingPhone', '60123456789');

    Cache::put('wa_login_otp:'.$user->id, [
        'hash' => hash('sha256', '654321'),
        'attempts' => 0,
    ], 600);

    $component
        ->set('data.otp', '654321')
        ->set('data.remember', true)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect('/admin');

    expect(collect(session('filament.notifications'))->pluck('title'))
        ->toContain('Signed in successfully');

    $this->assertAuthenticatedAs($user);
});

test('otp login rejects wrong code', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $component = Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp');

    Cache::put('wa_login_otp:'.$user->id, [
        'hash' => hash('sha256', '654321'),
        'attempts' => 0,
    ], 600);

    $component
        ->set('data.otp', '000000')
        ->call('authenticate')
        ->assertHasErrors(['data.otp']);

    $this->assertGuest();
});

test('password login step authenticates with email and password', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create([
        'email' => 'admin@tido.local',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->call('showPasswordStep')
        ->assertSet('loginMode', 'password')
        ->set('data.email', 'admin@tido.local')
        ->set('data.password', 'password')
        ->set('data.remember', true)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect('/admin')
        ->assertNotified('Signed in successfully');

    $this->assertAuthenticatedAs($user);
});

test('can switch between whatsapp and password login steps', function () {
    Livewire::test(Login::class)
        ->assertSet('loginMode', 'phone')
        ->assertSee('Send WhatsApp code')
        ->assertDontSee('Verify code & sign in')
        ->call('showPasswordStep')
        ->assertSet('loginMode', 'password')
        ->assertSee('Sign in')
        ->assertDontSee('Verify code & sign in')
        ->call('showPhoneStep')
        ->assertSet('loginMode', 'phone')
        ->assertSee('Send WhatsApp code')
        ->assertDontSee('Verify code & sign in');
});

test('user without matching personal number cannot access panel after otp', function () {
    $user = User::factory()->create([
        'phone' => '60987654321',
    ]);

    $panel = filament()->getPanel('admin');

    expect($user->canAccessPanel($panel))->toBeFalse();

    // Bypass send (would require matching allowlist phone); plant OTP and pending phone via reflection-free path:
    // use password allowlist mismatch by verifying through authenticate after manually advancing mode.
    // Send is allowed for any matching User.phone; panel access is checked at login.
    $component = Livewire::test(Login::class)
        ->set('data.phone', '60987654321')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp');

    Cache::put('wa_login_otp:'.$user->id, [
        'hash' => hash('sha256', '111111'),
        'attempts' => 0,
    ], 600);

    $component
        ->set('data.otp', '111111')
        ->call('authenticate')
        ->assertHasErrors(['data.otp']);

    $this->assertGuest();
});

test('otp service verify is used end to end after send', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $component = Livewire::test(Login::class)
        ->set('data.phone', '+60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp');

    Cache::put('wa_login_otp:'.$user->id, [
        'hash' => hash('sha256', '222333'),
        'attempts' => 0,
    ], 600);

    $component
        ->set('data.otp', '222333')
        ->call('authenticate')
        ->assertRedirect('/admin');

    expect(collect(session('filament.notifications'))->pluck('title'))
        ->toContain('Signed in successfully');

    $this->assertAuthenticatedAs($user);
});

test('resend otp sends a new code after cooldown clears', function () {
    User::factory()->withWhatsAppPhone('60123456789')->create();

    $component = Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp')
        ->assertSet('pendingPhone', '60123456789')
        ->assertSet('lastOtpPhone', '60123456789');

    expect($component->get('otpCooldownEndsAt'))->toBeInt()
        ->and($component->instance()->otpCooldownRemainingSeconds())->toBeGreaterThan(0);

    expect(
        collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/message/sendText/'))
            ->count()
    )->toBe(1);

    Cache::flush();
    $component->set('otpCooldownEndsAt', null);

    $component
        ->call('resendOtp')
        ->assertHasNoErrors()
        ->assertSet('loginMode', 'otp')
        ->assertNotified();

    expect(
        collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/message/sendText/'))
            ->count()
    )->toBe(2);
});

test('resend otp shows cooldown on otp field instead of silent failure', function () {
    User::factory()->withWhatsAppPhone('60123456789')->create();

    Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp')
        ->call('resendOtp')
        ->assertHasErrors(['data.otp'])
        ->assertNotified();
});

test('otp cooldown persists when returning to phone step for same number', function () {
    User::factory()->withWhatsAppPhone('60123456789')->create();

    $component = Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp')
        ->assertSee('Resend available in');

    $endsAt = $component->get('otpCooldownEndsAt');

    $component
        ->call('showPhoneStep')
        ->assertSet('loginMode', 'phone')
        ->assertSet('otpCooldownEndsAt', $endsAt)
        ->assertSet('lastOtpPhone', '60123456789')
        ->assertSet('data.phone', '60123456789')
        ->assertSee('Another code available in');

    expect($component->instance()->isPhoneSendOnCooldown())->toBeTrue();
});

test('otp cooldown clears after successful authentication', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $component = Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp');

    expect($component->get('otpCooldownEndsAt'))->toBeInt();

    Cache::put('wa_login_otp:'.$user->id, [
        'hash' => hash('sha256', '222333'),
        'attempts' => 0,
    ], 600);

    $component
        ->set('data.otp', '222333')
        ->call('authenticate')
        ->assertRedirect('/admin')
        ->assertSet('otpCooldownEndsAt', null)
        ->assertSet('lastOtpPhone', null);

    expect(collect(session('filament.notifications'))->pluck('title'))
        ->toContain('Signed in successfully');

    $this->assertAuthenticatedAs($user);
});

test('otp step shows different number link and mode tabs instead of password cta', function () {
    User::factory()->withWhatsAppPhone('60123456789')->create();

    $component = Livewire::test(Login::class)
        ->set('data.phone', '60123456789')
        ->call('sendOtp')
        ->assertSet('loginMode', 'otp')
        ->assertSee('One-Time Password (OTP)')
        ->assertSee('Email & Password')
        ->assertDontSee('Sign in with email & password')
        ->assertSee('Use a different number')
        ->assertSee('Resend in')
        ->assertSee('A One-Time Password (OTP) code has been sent via WhatsApp to 60123456789. You can use the OTP code here.')
        ->assertDontSeeHtml('>Use a different number</button>')
        ->call('showPhoneStep')
        ->assertSet('loginMode', 'phone')
        ->assertDontSee('Use a different number');

    expect($component->instance()->getSubheading())
        ->toBe('Where tidy preparation meets finished work, then "tido" (sleep).');
});

test('login mode tabs switch between otp and password flows', function () {
    Livewire::test(Login::class)
        ->assertSet('loginMode', 'phone')
        ->assertSee('One-Time Password (OTP)')
        ->assertSee('Email & Password')
        ->call('selectPasswordLoginTab')
        ->assertSet('loginMode', 'password')
        ->assertDontSee('Sign in with WhatsApp code')
        ->call('selectOtpLoginTab')
        ->assertSet('loginMode', 'phone');
});
