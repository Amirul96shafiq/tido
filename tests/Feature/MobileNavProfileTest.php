<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile personalize preferences section renders mobile nav toggle', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(EditProfile::class)
        ->assertSee('PREFERENCES', false)
        ->assertSee('Mobile Nav', false)
        ->assertSee('On small screens, replace the top bar with a bottom navigation bar. Save to keep this preference for future sign-ins.', false)
        ->assertSchemaComponentExists('personalize-preferences')
        ->assertSet('data.mobile_nav_enabled', false);

    $toggle = $component->instance()->form->getComponent('mobile_nav_enabled');

    expect($toggle?->getColumnSpan('default'))->toBe('full');
});

test('profile saves the mobile nav preference', function (bool $enabled): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => ! $enabled,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.mobile_nav_enabled', $enabled)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->mobile_nav_enabled)->toBe($enabled);
})->with([
    'enabled' => true,
    'disabled' => false,
]);

test('changing the mobile nav preference reports the profile change', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => false,
        'notify_profile_updates' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.mobile_nav_enabled', true)
        ->call('save')
        ->assertHasNoErrors();

    $notification = $user->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['body'])->toContain('Mobile Nav');
});

test('admin panel injects mobile nav script and class when preference is enabled', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('tidoSetMobileNav', false)
        ->assertSee("document.documentElement.classList.add('tido-mobilenav')", false)
        ->assertSee('tido-mobilenav', false)
        ->assertSee('open-global-search-modal', false)
        ->assertSee('fi-user-menu--mobilenav', false)
        ->assertSee('account-switcher-mobilenav', false);
});

test('admin panel does not inject mobile nav class when preference is disabled', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => false,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('tidoSetMobileNav', false)
        ->assertDontSee("document.documentElement.classList.add('tido-mobilenav')", false);
});

test('mobile nav user menu opens upward from the bottom avatar', function (): void {
    $userMenu = (string) file_get_contents(
        resource_path('views/vendor/filament-panels/components/user-menu.blade.php'),
    );

    expect($userMenu)
        ->toContain("'mobilenav' => 'top-end'")
        ->toContain("'mobilenav' => 8")
        ->toContain("in_array(\$anchor, ['topbar', 'mobilenav'], true)")
        ->toContain("key('account-switcher-'.\$userMenuInstanceKey)")
        ->toContain('fi-user-menu--');
});

test('family member mobile nav add sheet disables budget and recurring create links', function (): void {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $user = User::query()->where('family_member_id', $member->id)->first();

    expect($user)->not->toBeNull();

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('Add Receipt', false)
        ->assertSee('Add Budget', false)
        ->assertSee('Add Recurring', false)
        ->assertSee(HouseholdAccess::createDeniedMessage(), false);
});

test('mobile nav css hides topbar and offsets sticky chrome on small screens', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('html.tido-mobilenav')
        ->toContain('--tido-mobilenav-height')
        ->toContain('html.tido-mobilenav .fi-topbar-ctn')
        ->toContain('.tido-mobilenav-root')
        ->toContain('html.tido-mobilenav .tido-mobilenav-root')
        ->not->toContain(".tido-mobilenav {\n    display: none;\n}")
        ->toContain('display: none !important')
        ->toContain('tido-sticky-marker--bottom')
        ->toContain('var(--tido-mobilenav-height, 4rem) + 0.25rem');
});

test('mobile nav avatar notification badge overlays the wrap like the topbar', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $avatarChrome = Str::between(
        $css,
        'html.tido-mobilenav .fi-user-menu--mobilenav .fi-user-menu-avatar-wrap {',
        'html.tido-mobilenav .fi-user-menu--mobilenav .fi-avatar {',
    );

    expect($avatarChrome)
        ->toContain('@apply relative inline-flex')
        ->toContain('.fi-user-menu-notifications-badge {')
        ->toContain('@apply pointer-events-none absolute top-0 right-0 z-10 translate-x-0.5 -translate-y-0.5');
});

test('mobile nav bar dark chrome targets html.dark not a dark ancestor of html', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('html.dark.tido-mobilenav .tido-mobilenav-bar')
        ->toContain('html.dark.tido-mobilenav .tido-mobilenav-item')
        ->toContain('html.dark.tido-mobilenav .tido-mobilenav-item--primary')
        ->not->toContain('.dark html.tido-mobilenav');
});

test('admin panel mobile nav script syncs preference across spa navigation', function (): void {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)
        ->toContain('tidoMobileNav')
        ->toContain('tidoSetMobileNav')
        ->toContain('applyMobileNavFromStorage')
        ->toContain('livewire:navigated')
        ->toContain('livewire:navigating');
});
