<?php

declare(strict_types=1);

use App\Filament\Pages\GoogleOAuthPage;
use App\Filament\Support\IntegrationNavigation;
use App\Models\FamilyMember;
use App\Models\GoogleOAuthLoginLog;
use App\Models\GoogleOAuthSetting;
use App\Models\Household;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use App\Support\CurrentHousehold;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function enableGoogleOAuthInDatabase(): void
{
    GoogleOAuthSetting::platform()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);

    GoogleOAuthSettings::platform()->forgetCache();
}

beforeEach(function (): void {
    CurrentHousehold::clear();
    $this->actingAs(User::factory()->create(['household_id' => 1]));
});

afterEach(function (): void {
    CurrentHousehold::clear();
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

test('configure modal saves encrypted client secret for platform household', function (): void {
    Livewire::test(GoogleOAuthPage::class)
        ->callAction('configureSetup', data: [
            'client_id' => 'saved-client-id',
            'client_secret' => 'saved-client-secret',
            'has_saved_secret' => false,
        ])
        ->assertNotified('Google OAuth settings saved');

    $raw = GoogleOAuthSetting::platform()->getRawOriginal('client_secret');

    expect($raw)->not->toBe('saved-client-secret')
        ->and(GoogleOAuthSettings::platform()->clientSecret())->toBe('saved-client-secret')
        ->and(GoogleOAuthSettings::platform()->isSignInAvailable())->toBeTrue();
});

test('household two cannot save platform credentials', function (): void {
    $householdTwo = Household::factory()->create();
    $user = User::factory()->create(['household_id' => $householdTwo->id]);

    $this->actingAs($user);
    CurrentHousehold::set($householdTwo->id);

    Livewire::test(GoogleOAuthPage::class)
        ->assertSuccessful()
        ->assertActionHidden('configureSetup');
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

test('unlink google account clears current primary google id', function (): void {
    $user = auth()->user();
    assert($user instanceof User);

    $user->forceFill([
        'google_id' => 'google-sub-123',
        'google_linked_at' => now(),
    ])->save();

    Livewire::test(GoogleOAuthPage::class)
        ->callAction('unlinkGoogleAccount')
        ->assertNotified('Google account unlinked');

    expect($user->fresh())
        ->google_id->toBeNull()
        ->google_linked_at->toBeNull();
});

test('reset credentials clears shared settings without clearing other household links', function (): void {
    enableGoogleOAuthInDatabase();

    $householdTwo = Household::factory()->create();
    $other = User::factory()->create([
        'household_id' => $householdTwo->id,
        'google_id' => 'google-sub-456',
        'google_linked_at' => now(),
    ]);

    Livewire::test(GoogleOAuthPage::class)
        ->callAction('resetCredentials')
        ->assertNotified('Google OAuth credentials reset');

    expect(GoogleOAuthSettings::platform()->hasCredentials())->toBeFalse()
        ->and($other->fresh()->google_id)->toBe('google-sub-456');
});

test('header overflow uses the same gray button as other integration pages', function (): void {
    $group = collect(Livewire::test(GoogleOAuthPage::class)->instance()->getCachedHeaderActions())
        ->first(fn (mixed $action): bool => $action instanceof ActionGroup);

    expect($group)->toBeInstanceOf(ActionGroup::class)
        ->and($group->isButton())->toBeTrue()
        ->and($group->getColor())->toBe('gray')
        ->and($group->getIcon())->toBe('heroicon-m-ellipsis-vertical')
        ->and($group->getLabel())->toBe('');
});
