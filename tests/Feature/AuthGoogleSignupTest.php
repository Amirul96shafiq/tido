<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Models\GoogleOAuthSetting;
use App\Models\Household;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use App\Services\GoogleOAuth\GoogleOAuthSignupPendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.google.redirect' => null]);
    Cache::flush();
});

function enableGoogleSignupOAuth(): void
{
    GoogleOAuthSetting::platform()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);

    GoogleOAuthSettings::platform()->forgetCache();
}

function fakeGoogleUser(string $id, string $email, bool $emailVerified = true): SocialiteUser
{
    return tap(SocialiteUser::fake([
        'id' => $id,
        'name' => 'Google Primary',
        'email' => $email,
    ]), function (SocialiteUser $user) use ($id, $email, $emailVerified): void {
        $user->setRaw([
            'sub' => $id,
            'email' => $email,
            'email_verified' => $emailVerified,
        ]);
    });
}

test('register page shows enabled continue with google when platform credentials exist', function (): void {
    enableGoogleSignupOAuth();

    Livewire::test(Register::class)
        ->assertSee('Continue with Google')
        ->assertSee('wire:click="continueWithGoogle"', false)
        ->assertDontSee('tido-auth-google-sign-in-btn--disabled', false);
});

test('register page hides continue with google when credentials missing', function (): void {
    Livewire::test(Register::class)
        ->assertDontSee('Continue with Google');
});

test('google signup callback stores pending signup for new email', function (): void {
    enableGoogleSignupOAuth();

    $this->withSession([
        GoogleOAuthController::SESSION_INTENT_KEY => GoogleOAuthController::INTENT_SIGNUP,
    ]);

    Socialite::fake('google', fakeGoogleUser('google-sub-new-signup', 'newgoogle@example.com'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.register'));

    expect(User::query()->where('email', 'newgoogle@example.com')->exists())->toBeFalse();

    $this->assertGuest();

    Livewire::test(Register::class)
        ->assertSet('authPanel', 'sign-up')
        ->assertSet('googleVerifiedSignupEmail', 'newgoogle@example.com')
        ->assertSee('Verified');
});

test('google verified signup completes registration with linked google id', function (): void {
    enableGoogleSignupOAuth();

    $existing = User::factory()->create(['household_id' => 1]);

    session([
        GoogleOAuthSignupPendingService::SESSION_KEY => [
            'google_id' => 'google-sub-complete',
            'email' => 'complete-google@example.com',
            'name' => 'Complete Google',
        ],
        'google_oauth_signup_panel' => true,
    ]);

    Livewire::test(Register::class)
        ->assertSet('googleVerifiedSignupEmail', 'complete-google@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('completeGoogleSignup')
        ->assertRedirect();

    $user = User::query()->where('email', 'complete-google@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('google-sub-complete')
        ->and($user->google_linked_at)->not->toBeNull()
        ->and($user->household_id)->not->toBe(1)
        ->and($user->isPrimary())->toBeTrue()
        ->and(Household::query()->whereKey($user->household_id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($existing->id)->value('household_id'))->toBe(1);

    $this->assertAuthenticatedAs($user);
});

test('google verified signup uses pending email even when form email is tampered', function (): void {
    enableGoogleSignupOAuth();

    session([
        GoogleOAuthSignupPendingService::SESSION_KEY => [
            'google_id' => 'google-sub-tamper',
            'email' => 'verified-google@example.com',
            'name' => 'Verified Google',
        ],
        'google_oauth_signup_panel' => true,
    ]);

    Livewire::test(Register::class)
        ->set('data.email', 'tampered@example.com')
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('completeGoogleSignup')
        ->assertRedirect();

    expect(User::query()->where('email', 'verified-google@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'tampered@example.com')->exists())->toBeFalse();
});

test('google verified signup fails when pending session expired', function (): void {
    enableGoogleSignupOAuth();

    Livewire::test(Register::class)
        ->set('data.password', 'password-password')
        ->set('data.password_confirmation', 'password-password')
        ->call('completeGoogleSignup')
        ->assertHasErrors(['data.email']);
});

test('google callback prefers linked google id over a different oauth email', function (): void {
    enableGoogleSignupOAuth();

    $linkedUser = User::factory()->create([
        'email' => 'linked@example.com',
        'google_id' => 'google-sub-linked',
        'google_linked_at' => now(),
        'household_id' => 1,
    ]);

    User::factory()->create([
        'email' => 'owner@example.com',
        'google_id' => null,
        'household_id' => 1,
    ]);

    Socialite::fake('google', fakeGoogleUser('google-sub-linked', 'owner@example.com'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($linkedUser);

    expect(User::query()->where('email', 'owner@example.com')->value('google_id'))->toBeNull();
});

test('google signup with existing primary email signs in and links google id', function (): void {
    enableGoogleSignupOAuth();

    $user = User::factory()->create([
        'email' => 'existing-primary@example.com',
        'google_id' => null,
        'household_id' => 1,
    ]);

    $this->withSession([
        GoogleOAuthController::SESSION_INTENT_KEY => GoogleOAuthController::INTENT_SIGNUP,
    ]);

    Socialite::fake('google', fakeGoogleUser('google-sub-existing', 'existing-primary@example.com'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);

    expect($user->fresh())
        ->google_id->toBe('google-sub-existing')
        ->google_linked_at->not->toBeNull();

    expect(User::query()->count())->toBe(1);
});

test('google signup redirect stores signup intent in session', function (): void {
    enableGoogleSignupOAuth();

    Socialite::fake('google');

    $this->get(route('filament.admin.auth.google.redirect', ['intent' => 'signup']))
        ->assertRedirect();

    expect(session(GoogleOAuthController::SESSION_INTENT_KEY))->toBe(GoogleOAuthController::INTENT_SIGNUP);
});

test('google signup callback hands off pending signup to app url when redirect host differs', function (): void {
    config([
        'app.url' => 'http://tido.local',
        'services.google.redirect' => 'http://localhost/admin/auth/google/callback',
    ]);

    enableGoogleSignupOAuth();

    $this->withSession([
        GoogleOAuthController::SESSION_INTENT_KEY => GoogleOAuthController::INTENT_SIGNUP,
    ]);

    Socialite::fake('google', fakeGoogleUser('google-sub-cross-host', 'cross-host@example.com'));

    $callbackResponse = $this->get('http://localhost/admin/auth/google/callback');

    $callbackResponse->assertRedirect();

    $redirectUrl = (string) $callbackResponse->headers->get('Location');

    expect($redirectUrl)->toStartWith('http://tido.local/admin/auth/google/complete?token=');

    $this->get($redirectUrl)
        ->assertRedirect(route('filament.admin.auth.register'));

    Livewire::test(Register::class)
        ->assertSet('authPanel', 'sign-up')
        ->assertSet('googleVerifiedSignupEmail', 'cross-host@example.com')
        ->assertSee('Verified');
});

test('login page redirects to register when google signup pending session exists', function (): void {
    session([
        GoogleOAuthSignupPendingService::SESSION_KEY => [
            'google_id' => 'google-sub-redirect',
            'email' => 'pending@example.com',
            'name' => 'Pending Google',
        ],
        'google_oauth_signup_panel' => true,
    ]);

    Livewire::test(Login::class)
        ->assertRedirect(route('filament.admin.auth.register'));
});
