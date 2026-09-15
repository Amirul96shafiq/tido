<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Register;
use App\Models\GoogleOAuthSetting;
use App\Models\Household;
use App\Models\User;
use App\Notifications\EmailSignupOtpNotification;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Notification::fake();

    config([
        'services.email_signup.dev_otp' => '654321',
        'services.email_signup.dev_addresses' => 'dev-signup@example.com',
    ]);
});

test('sign up validates email password and confirmation', function (): void {
    Livewire::test(Register::class)
        ->set('data.email', 'not-an-email')
        ->set('data.password', 'short')
        ->set('data.password_confirmation', 'mismatch')
        ->call('sendSignupOtp')
        ->assertHasErrors([
            'data.email',
            'data.password',
        ]);
});

test('sign up sends email code and moves to otp step', function (): void {
    Livewire::test(Register::class)
        ->set('data.email', 'dev-signup@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('sendSignupOtp')
        ->assertSet('signupMode', 'otp')
        ->assertSet('pendingSignupEmail', 'dev-signup@example.com')
        ->assertSee('Enter The Code')
        ->assertDontSee('tido-signup-greeting-heading', false)
        ->assertSee('Confirmation code');

    Notification::assertNothingSent();
});

test('sign up rejects emails that already belong to a user', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Register::class)
        ->set('data.email', 'taken@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('sendSignupOtp')
        ->assertHasErrors(['data.email']);
});

test('sign up queues otp mail for non dev addresses', function (): void {
    Livewire::test(Register::class)
        ->set('data.email', 'brand-new@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('sendSignupOtp')
        ->assertSet('signupMode', 'otp');

    Notification::assertSentOnDemand(EmailSignupOtpNotification::class);
});

test('sign up creates a new household primary after otp verification', function (): void {
    $existing = User::factory()->create(['household_id' => 1]);

    Livewire::test(Register::class)
        ->set('data.email', 'dev-signup@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('sendSignupOtp')
        ->set('data.otp', '654321')
        ->call('completeSignup')
        ->assertRedirect();

    $user = User::query()->where('email', 'dev-signup@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->household_id)->not->toBe(1)
        ->and($user->isPrimary())->toBeTrue()
        ->and(Household::query()->whereKey($user->household_id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($existing->id)->value('household_id'))->toBe(1);
});

test('sign up rejects invalid otp codes', function (): void {
    Livewire::test(Register::class)
        ->set('data.email', 'dev-signup@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('sendSignupOtp')
        ->set('data.otp', '000000')
        ->call('completeSignup')
        ->assertHasErrors(['data.otp']);
});

test('sign up resend is blocked during cooldown', function (): void {
    Livewire::test(Register::class)
        ->set('data.email', 'dev-signup@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('sendSignupOtp')
        ->call('resendSignupOtp')
        ->assertHasErrors(['data.otp']);
});

test('switching back to sign in resets signup state', function (): void {
    Livewire::test(Register::class)
        ->set('data.email', 'dev-signup@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('sendSignupOtp')
        ->assertSet('signupMode', 'otp')
        ->call('selectSignInTab')
        ->assertRedirect(route('filament.admin.auth.login'));
});

test('sign up shows disabled continue with google when credentials missing', function (): void {
    $html = Livewire::test(Register::class)->html();

    expect($html)
        ->not->toContain('wire:click="continueWithGoogle"');
});

test('sign up shows enabled continue with google when credentials exist', function (): void {
    GoogleOAuthSetting::platform()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);
    GoogleOAuthSettings::platform()->forgetCache();

    $html = Livewire::test(Register::class)->html();

    expect($html)
        ->toContain('wire:click="continueWithGoogle"')
        ->not->toContain('tido-auth-google-sign-in-btn--disabled');
});
