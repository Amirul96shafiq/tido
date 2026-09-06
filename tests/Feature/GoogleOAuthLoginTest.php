<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Models\FamilyMember;
use App\Models\GoogleOAuthLoginLog;
use App\Models\GoogleOAuthSetting;
use App\Models\Household;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.google.redirect' => null]);
});

function enablePlatformGoogleOAuth(): void
{
    GoogleOAuthSetting::platform()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);

    GoogleOAuthSettings::platform()->forgetCache();
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

test('login page does not show continue with google when credentials missing', function (): void {
    Livewire::test(Login::class)
        ->set('loginMode', 'password')
        ->assertSuccessful()
        ->assertDontSee('Continue with Google');
});

test('login page always shows continue with google when platform credentials exist', function (): void {
    enablePlatformGoogleOAuth();

    Livewire::test(Login::class)
        ->set('loginMode', 'password')
        ->assertSuccessful()
        ->assertSee('Continue with Google')
        ->assertSee('or');
});

test('login page shows continue with google on otp phone step', function (): void {
    enablePlatformGoogleOAuth();

    Livewire::test(Login::class)
        ->set('loginMode', 'phone')
        ->assertSuccessful()
        ->assertSee('Continue with Google')
        ->assertSee('or');
});

test('login page shows continue with google on otp code step', function (): void {
    enablePlatformGoogleOAuth();

    Livewire::test(Login::class)
        ->set('loginMode', 'otp')
        ->assertSuccessful()
        ->assertSee('Continue with Google')
        ->assertSee('or');
});

test('google redirect route is unavailable when credentials missing', function (): void {
    $this->get(route('filament.admin.auth.google.redirect'))
        ->assertRedirect(route('filament.admin.auth.login'));
});

test('google redirect route redirects when platform oauth is configured', function (): void {
    enablePlatformGoogleOAuth();

    Socialite::fake('google');

    $this->get(route('filament.admin.auth.google.redirect'))
        ->assertRedirect();
});

test('google callback signs in primary already linked by google id', function (): void {
    enablePlatformGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => 'google-sub-200',
        'google_linked_at' => now()->subDay(),
        'household_id' => 1,
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-200', 'admin@tido.local'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);

    Notification::assertNotified('Signed in successfully, via Google Account');
});

test('google callback rejects unlinked primary even when email matches', function (): void {
    enablePlatformGoogleOAuth();

    User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => null,
        'household_id' => 1,
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-100', 'admin@tido.local'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();

    expect(GoogleOAuthLoginLog::query()->where('status', 'failed')->count())->toBe(1);
});

test('google callback rejects unknown google identity', function (): void {
    enablePlatformGoogleOAuth();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-unknown', 'unknown@example.com'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();

    Livewire::test(Login::class)
        ->assertNotified('Google sign-in failed');
});

test('google callback rejects family member accounts', function (): void {
    enablePlatformGoogleOAuth();

    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $familyUser->forceFill([
        'google_id' => 'google-sub-family',
        'google_linked_at' => now(),
    ])->save();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-family', $familyUser->email));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});

test('google callback rejects unverified google email', function (): void {
    enablePlatformGoogleOAuth();

    User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => 'google-sub-unverified',
        'google_linked_at' => now(),
        'household_id' => 1,
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-unverified', 'admin@tido.local', false));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});

test('google callback does not create a new user', function (): void {
    enablePlatformGoogleOAuth();

    $before = User::query()->count();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-new', 'newperson@example.com'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(User::query()->count())->toBe($before);
});

test('authenticated primary can link google account', function (): void {
    enablePlatformGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => null,
        'household_id' => 1,
    ]);

    $this->actingAs($user);

    Socialite::fake('google');

    $this->get(route('filament.admin.auth.google.link'))
        ->assertRedirect();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-link', 'admin@tido.local'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect();

    expect($user->fresh())
        ->google_id->toBe('google-sub-link')
        ->google_linked_at->not->toBeNull();
});

test('link handoff moves oauth to redirect host when app host differs', function (): void {
    config([
        'app.url' => 'http://tido.local',
        'services.google.redirect' => 'http://localhost/admin/auth/google/callback',
    ]);

    enablePlatformGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'hh2@link.test',
        'google_id' => null,
        'household_id' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->get('http://tido.local/admin/auth/google/link');

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');

    expect($location)
        ->toStartWith('http://localhost/admin/auth/google/link?token=');

    Socialite::fake('google');

    $this->get($location)
        ->assertRedirect();

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-hh2-link', 'hh2@link.test'));

    $this->get('http://localhost/admin/auth/google/callback')
        ->assertRedirect();

    expect($user->fresh())
        ->google_id->toBe('google-sub-hh2-link')
        ->google_linked_at->not->toBeNull();
});

test('redirect url uses configured google redirect uri', function (): void {
    config([
        'services.google.redirect' => 'http://localhost/admin/auth/google/callback',
    ]);

    expect(GoogleOAuthSettings::platform()->redirectUrl())
        ->toBe('http://localhost/admin/auth/google/callback')
        ->and(GoogleOAuthSettings::platform()->authorizeUrl())
        ->toBe('http://localhost/admin/auth/google/redirect');
});

test('google callback hands off session to app url when redirect host differs', function (): void {
    config([
        'app.url' => 'http://tido.local',
        'services.google.redirect' => 'http://localhost/admin/auth/google/callback',
    ]);

    enablePlatformGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'admin@tido.local',
        'google_id' => 'google-sub-handoff',
        'google_linked_at' => now()->subDay(),
        'household_id' => 1,
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

    Notification::assertNotified('Signed in successfully, via Google Account');
});

test('google sign in wrapper balances gap above and below divider', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $wrapBlock = Str::between($css, '.tido-auth-google-sign-in-wrap {', '}');
    $dividerBlock = Str::between($css, '.tido-auth-google-divider {', '}');

    expect($wrapBlock)
        ->toContain('margin-top: -0.5rem;')
        ->and($dividerBlock)
        ->toContain('margin-bottom: 1rem;');
});

test('household two linked primary can sign in with shared client', function (): void {
    enablePlatformGoogleOAuth();

    $householdTwo = Household::factory()->create();
    $user = User::factory()->create([
        'email' => 'hh2@tido.local',
        'household_id' => $householdTwo->id,
        'google_id' => 'google-sub-hh2',
        'google_linked_at' => now(),
    ]);

    Socialite::fake('google', fakeGoogleSocialiteUser('google-sub-hh2', 'hh2@tido.local'));

    $this->get(route('filament.admin.auth.google.callback'))
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});
