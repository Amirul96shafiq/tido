<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Models\FamilyMember;
use App\Models\GoogleOAuthLoginLog;
use App\Models\GoogleOAuthSetting;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.google.redirect' => null]);
});

function enableGoogleOAuthForLogin(): void
{
    GoogleOAuthSetting::singleton()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);

    app(GoogleOAuthSettings::class)->forgetCache();
}

function fakeGoogleSocialiteUser(string $id, string $email, bool $emailVerified = true): SocialiteUser
{
    return tap(SocialiteUser::fake([
        'id' => $id,
        'name' => 'Primary User',
        'email' => $email,
    ]), function (SocialiteUser $user) use ($id, $email, $emailVerified): void {
        $user->setRaw([
            'sub' => $id,
            'email' => $email,
            'email_verified' => $emailVerified,
        ]);
    });
}

test('login page does not show continue with google when oauth is disabled', function (): void {
    Livewire::test(Login::class)
        ->assertSuccessful()
        ->assertDontSee('Continue with Google');
});

test('login page shows continue with google when oauth is enabled', function (): void {
    enableGoogleOAuthForLogin();

    Livewire::test(Login::class)
        ->assertSuccessful()
        ->assertSee('Continue with Google');
});

test('login page hides continue with google on otp step', function (): void {
    enableGoogleOAuthForLogin();

    Livewire::test(Login::class)
        ->set('loginMode', 'otp')
        ->assertDontSee('Continue with Google');
});

test('google redirect route is unavailable when oauth is disabled', function (): void {
    $this->get(route('filament.admin.auth.google.redirect'))
        ->assertRedirect(route('filament.admin.auth.not-found'));
});

test('google redirect route redirects when oauth is enabled', function (): void {
    enableGoogleOAuthForLogin();

    Socialite::fake('google');

    $this->get(route('filament.admin.auth.google.redirect'))
        ->assertRedirect();
});

test('google callback links primary user by email on first sign in', function (): void {
    enableGoogleOAuthForLogin();

    $user = User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => null,
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-100', 'admin@tido.local'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);

    expect($user->fresh())
        ->google_id->toBe('google-sub-100')
        ->google_linked_at->not->toBeNull();

    expect(GoogleOAuthLoginLog::query()->where('status', 'success')->count())->toBe(1);
});

test('google callback signs in primary user by google id on subsequent sign in', function (): void {
    enableGoogleOAuthForLogin();

    $user = User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => 'google-sub-200',
        'google_linked_at' => now()->subDay(),
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-200', 'admin@tido.local'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

test('google callback rejects unknown email with generic login error', function (): void {
    enableGoogleOAuthForLogin();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-unknown', 'unknown@example.com'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();

    expect(GoogleOAuthLoginLog::query()->where('status', 'failed')->count())->toBe(1);

    Livewire::test(Login::class)
        ->assertNotified('Google sign-in failed');
});

test('google callback rejects family member accounts', function (): void {
    enableGoogleOAuthForLogin();

    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-family', $familyUser->email));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});

test('google callback rejects unverified google email', function (): void {
    enableGoogleOAuthForLogin();

    User::factory()->create([
        'email' => 'admin@tido.local',
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-unverified', 'admin@tido.local', false));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});

test('google callback does not create a new user', function (): void {
    enableGoogleOAuthForLogin();

    $before = User::query()->count();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-new', 'newperson@example.com'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(User::query()->count())->toBe($before);
});

test('redirect url uses configured google redirect uri', function (): void {
    config([
        'services.google.redirect' => 'http://localhost/admin/auth/google/callback',
    ]);

    expect(app(GoogleOAuthSettings::class)->redirectUrl())
        ->toBe('http://localhost/admin/auth/google/callback')
        ->and(app(GoogleOAuthSettings::class)->authorizeUrl())
        ->toBe('http://localhost/admin/auth/google/redirect');
});

test('login page links continue with google to localhost when redirect uri is configured', function (): void {
    config([
        'app.url' => 'http://tido.local',
        'services.google.redirect' => 'http://localhost/admin/auth/google/callback',
    ]);

    enableGoogleOAuthForLogin();

    Livewire::test(Login::class)
        ->assertSuccessful()
        ->assertSee('http://localhost/admin/auth/google/redirect', false);
});

test('google callback hands off session to app url when redirect host differs', function (): void {
    config([
        'app.url' => 'http://tido.local',
        'services.google.redirect' => 'http://localhost/admin/auth/google/callback',
    ]);

    enableGoogleOAuthForLogin();

    $user = User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => 'google-sub-handoff',
        'google_linked_at' => now()->subDay(),
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-handoff', 'admin@tido.local'));

    $callbackResponse = $this->get('http://localhost/admin/auth/google/callback');

    $callbackResponse->assertRedirect();

    $this->assertGuest();

    $redirectUrl = $callbackResponse->headers->get('Location');

    expect($redirectUrl)->toStartWith('http://tido.local/admin/auth/google/complete?token=');

    $this->get($redirectUrl)
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});
