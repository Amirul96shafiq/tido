<?php

declare(strict_types=1);

use App\Filament\Pages\GoogleOAuthPage;
use App\Filament\Support\IntegrationNavigation;
use App\Models\FamilyMember;
use App\Models\GoogleOAuthLoginLog;
use App\Models\GoogleOAuthSetting;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function enableGoogleOAuthInDatabase(): void
{
    GoogleOAuthSetting::singleton()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);

    app(GoogleOAuthSettings::class)->forgetCache();
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('google oauth page renders for primary household', function (): void {
    Livewire::test(GoogleOAuthPage::class)
        ->assertSuccessful()
        ->assertSee('Status')
        ->assertSee('Configuration')
        ->assertSee('Readiness')
        ->assertSee('Sign-In History');
});

test('google oauth page navigation is registered under google parent', function (): void {
    expect(GoogleOAuthPage::getNavigationGroup())->toBe(IntegrationNavigation::GROUP)
        ->and(GoogleOAuthPage::getNavigationParentItem())->toBe(IntegrationNavigation::GOOGLE)
        ->and(GoogleOAuthPage::getNavigationLabel())->toBe('Google OAuth')
        ->and(GoogleOAuthPage::getNavigationSort())->toBe(10);
});

test('family members cannot access google oauth page', function (): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    expect(GoogleOAuthPage::canAccess())->toBeFalse();

    $this->get(GoogleOAuthPage::getUrl())
        ->assertRedirect();
});

test('configure modal saves encrypted client secret', function (): void {
    Livewire::test(GoogleOAuthPage::class)
        ->callAction('configureSetup', data: [
            'client_id' => 'saved-client-id',
            'client_secret' => 'saved-client-secret',
            'has_saved_secret' => false,
            'enabled' => true,
        ])
        ->assertNotified('Google OAuth settings saved');

    $raw = GoogleOAuthSetting::query()->first()?->getRawOriginal('client_secret');

    expect($raw)->not->toBe('saved-client-secret')
        ->and(app(GoogleOAuthSettings::class)->clientSecret())->toBe('saved-client-secret')
        ->and(app(GoogleOAuthSettings::class)->enabled())->toBeTrue();
});

test('test connection uses google token endpoint', function (): void {
    enableGoogleOAuthInDatabase();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_grant',
        ], 400),
    ]);

    Livewire::test(GoogleOAuthPage::class)
        ->call('testConnection')
        ->assertNotified('Connection verified');

    expect(GoogleOAuthLoginLog::query()->count())->toBe(0);
});

test('unlink google account clears primary google id', function (): void {
    $user = User::factory()->create([
        'google_id' => 'google-sub-123',
        'google_linked_at' => now(),
    ]);

    Livewire::test(GoogleOAuthPage::class)
        ->callAction('unlinkGoogleAccount')
        ->assertNotified('Google account unlinked');

    expect($user->fresh())
        ->google_id->toBeNull()
        ->google_linked_at->toBeNull();
});

test('reset credentials clears settings and linked google account', function (): void {
    enableGoogleOAuthInDatabase();

    $user = User::factory()->create([
        'google_id' => 'google-sub-456',
        'google_linked_at' => now(),
    ]);

    Livewire::test(GoogleOAuthPage::class)
        ->callAction('resetCredentials')
        ->assertNotified('Google OAuth credentials reset');

    expect(app(GoogleOAuthSettings::class)->hasCredentials())->toBeFalse()
        ->and($user->fresh()->google_id)->toBeNull();
});
