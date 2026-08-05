<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Helpers\GitHelper;
use App\Models\FamilyMember;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Livewire\Topbar;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('user menu orders profile changelogs notifications and logout', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $items = Filament::getUserMenuItems();
    $keys = array_keys($items);

    expect($keys)->toBe(['profile', 'changelogs', 'notifications', 'logout']);

    expect($items['profile']->getIcon())->toBe('heroicon-o-user');
    expect($items['profile']->getSort())->toBeGreaterThanOrEqual(0);
    expect($items['changelogs']->getLabel())->toBe('Changelogs 🡥');
    expect($items['changelogs']->getSort())->toBeGreaterThan($items['profile']->getSort());
    expect($items['notifications']->getLabel())->toBe('Notifications 🡥');
    expect($items['notifications']->getIcon())->toBe('heroicon-o-bell');
    expect($items['notifications']->getSort())->toBeGreaterThan($items['changelogs']->getSort());
    expect($items['logout']->getSort())->toBeGreaterThan($items['notifications']->getSort());
    expect($items['logout']->getIcon())->toBe('heroicon-o-arrow-right-start-on-rectangle');
    expect($items['logout']->getColor())->toBe('danger');
    expect($items['logout']->isConfirmationRequired())->toBeTrue();
    expect($items['logout']->getModalHeading())->toBe('Sign out');
    expect($items['logout']->getModalDescription())->toBe('Are you sure you want to sign out of your account?');
    expect($items['logout']->getModalSubmitActionLabel())->toBe('Sign out');
    expect($items['logout']->hasAction())->toBeTrue();
    expect($items['logout']->getUrl())->toBeNull();
});

test('user menu logout confirmation signs out after confirm', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Topbar::class)
        ->callAction('logout')
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();

    Notification::assertNotified('Signed out successfully');
});

test('user menu logout mounts confirmation without signing out', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Topbar::class)
        ->mountAction('logout')
        ->assertMountedActionModalSee('Are you sure you want to sign out of your account?');

    $this->assertAuthenticatedAs($user);
});

test('user menu displays the app version and sidebar footer owns collapse controls', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    $response = $this->get(Dashboard::getUrl());

    $response->assertSuccessful();
    $response->assertSee('fi-user-menu-version-footer', false);
    $response->assertSee('tido App', false);
    $response->assertSee(GitHelper::getVersionString(), false);
    $response->assertSee('fi-sidebar-collapse-footer', false);
    $response->assertSee('fi-sidebar-close-collapse-sidebar-btn', false);
    $response->assertSee('fi-sidebar-open-collapse-sidebar-btn', false);
    $response->assertSee('Collapse sidebar', false);
    $response->assertDontSee('fi-sidebar-version-footer', false);
    $response->assertDontSee('fi-sidebar-version-expanded', false);
    $response->assertDontSee('fi-sidebar-version-collapsed', false);
});

test('theme switcher keeps user menu open by not calling close', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    $response = $this->get(Dashboard::getUrl());

    $response->assertSuccessful();
    $response->assertSee('fi-theme-switcher-btn', false);
    $response->assertSee('theme = \'light\'', false);
    $response->assertSee('theme = \'dark\'', false);
    $response->assertSee('theme = \'system\'', false);
    $response->assertDontSee('&& close()', false);
});

test('user menu places account switcher between profile details and theme selection', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create([
        'name' => 'Primary Account',
    ]);
    FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
        'display_name' => 'Spouse',
    ]);

    $this->actingAs($primary);

    $response = $this->get(Dashboard::getUrl());

    $response
        ->assertSuccessful()
        ->assertSee('fi-user-menu-profile-preview-meta', false)
        ->assertSee('Swap Account', false)
        ->assertSee('fi-account-switcher-section', false)
        ->assertSee('Spouse', false)
        ->assertSee('fi-account-switcher-account-chevron', false)
        ->assertSee('tido-single-line-text-clip', false)
        ->assertSee('x-ref="singleLineText"', false)
        ->assertDontSee('tido-text-marquee', false)
        ->assertSee('fi-theme-switcher-btn', false)
        ->assertDontSee('fi-account-switcher-trigger', false);

    $html = (string) $response->getContent();
    $profileDetailsPosition = strpos($html, 'fi-user-menu-profile-preview-meta');
    $accountSwitcherPosition = strpos($html, 'fi-account-switcher-section');
    $themeSwitcherPosition = strpos($html, 'fi-theme-switcher-btn');

    expect($profileDetailsPosition)->toBeInt()
        ->and($accountSwitcherPosition)->toBeInt()
        ->and($themeSwitcherPosition)->toBeInt()
        ->and($accountSwitcherPosition)->toBeGreaterThan($profileDetailsPosition)
        ->and($accountSwitcherPosition)->toBeLessThan($themeSwitcherPosition);

    expect(file_get_contents(resource_path('views/vendor/filament-panels/components/user-menu.blade.php')))
        ->toContain("@livewire(\\App\\Filament\\Livewire\\AccountSwitcher::class, key('account-switcher'))");

    expect(file_get_contents(resource_path('views/filament/livewire/partials/account-switcher-account.blade.php')))
        ->toContain('heroicon-m-chevron-right');
});

test('topbar hides notification bell and exposes notifications in user menu', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    Notification::make()
        ->title('Test notification')
        ->sendToDatabase($user);

    $response = $this->get(Dashboard::getUrl());

    $response->assertSuccessful();
    $response->assertDontSee('fi-topbar-database-notifications-btn', false);
    $response->assertSee('fi-topbar-database-notifications-trigger-sync', false);
    $response->assertSee('fi-user-menu-notifications-badge', false);
    $response->assertSee('fi-user-menu-avatar-wrap', false);
    $response->assertSee('fi-user-menu-notifications-wrap', false);
    $response->assertSee('fi-user-menu-item-notifications-badge', false);
    $response->assertSee('fi-user-menu-profile-preview-avatar', false);
    $response->assertSee('Notifications', false);
    $response->assertSee("\$dispatch('open-modal', { id: 'database-notifications' })", false);
    $response->assertSee('menuOpen', false);
    $response->assertSee("getAttribute('aria-expanded') === 'true'", false);
    $response->assertDontSee("dropdownTrigger.getAttribute('aria-expanded') === 'true'", false);
    $response->assertSee('offset: -39', false);
});

test('user menu profile preview shows email for primary user', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create([
        'email' => 'primary@tido.local',
    ]);

    $this->actingAs($user);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('primary@tido.local', false);
});

test('user menu profile preview hides email for family member', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60111222333',
        'name' => 'Nor Ezrieana Harun',
    ]);
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('Nor Ezrieana Harun', false)
        ->assertSee('60111222333', false)
        ->assertDontSee($user->email, false)
        ->assertDontSee('family+'.$member->id.'@tido.local', false);
});

test('user menu profile item uses wire current for spa-safe active state', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    $dashboardResponse = $this->get(Dashboard::getUrl());
    $dashboardResponse->assertSuccessful();
    $dashboardResponse->assertSee('wire:current="fi-user-menu-profile-active"', false);

    $profileResponse = $this->get(EditProfile::getUrl());
    $profileResponse->assertSuccessful();
    $profileResponse->assertSee('wire:current="fi-user-menu-profile-active"', false);
});

test('topbar user menu chrome matches collapsed sidebar square with left border', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $expectedSize = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';
    $block = Str::between($css, '.fi-topbar .fi-user-menu {', '.dark .fi-topbar .fi-user-menu {');
    $triggerBlock = Str::between(
        $css,
        '.fi-topbar .fi-user-menu-trigger {',
        '.fi-topbar .fi-user-menu-trigger .fi-user-menu-avatar-wrap {',
    );
    $notificationsWrapBlock = Str::between(
        $css,
        '.fi-user-menu-notifications-wrap {',
        '.fi-user-menu-notifications-wrap .fi-user-menu-item-notifications-badge {',
    );
    $itemBadgeBlock = Str::between(
        $css,
        '.fi-user-menu-notifications-wrap .fi-user-menu-item-notifications-badge {',
        '.fi-topbar-end .fi-no-database > .fi-modal-trigger {',
    );
    $profilePreviewBlock = Str::between(
        $css,
        '.fi-user-menu-profile-preview {',
        '.fi-user-menu-profile-preview-avatar {',
    );
    $profileAvatarBlock = Str::between(
        $css,
        '.fi-user-menu-profile-preview-avatar {',
        '.fi-user-menu-profile-preview-avatar .fi-avatar {',
    );
    $profileAvatarSizeBlock = Str::between(
        $css,
        '.fi-user-menu-profile-preview-avatar .fi-avatar {',
        '.fi-user-menu-profile-preview-name {',
    );
    $accountSwitcherSectionBlock = Str::between(
        $css,
        '.fi-account-switcher-section {',
        '.dark .fi-account-switcher-section {',
    );
    $accountSwitcherHeadingBlock = Str::between(
        $css,
        '.fi-account-switcher-heading {',
        '.dark .fi-account-switcher-heading {',
    );
    $accountSwitcherExpandedBlock = Str::between(
        $css,
        '.fi-account-switcher-expanded {',
        '.dark .fi-account-switcher-expanded {',
    );
    $accountSwitcherExpandedListBlock = Str::between(
        $css,
        '.fi-account-switcher-expanded-list {',
        '.fi-account-switcher-cta {',
    );
    $accountSwitcherMobileBlock = Str::between(
        $css,
        '@media (max-width: 639px) {',
        '/* Skip layout/chrome motion on the first paint after a hard refresh */',
    );
    $accountSwitcherCtaBlock = Str::between(
        $css,
        '.fi-account-switcher-cta {',
        '.dark .fi-account-switcher-cta {',
    );
    $accountSwitcherFadeBlock = Str::between(
        $css,
        '.fi-account-switcher-account-preview-faded {',
        '.fi-account-switcher-account-avatar {',
    );
    $accountSwitcherAccountBlock = Str::between(
        $css,
        '.fi-account-switcher-account {',
        '.fi-account-switcher-account:hover {',
    );
    $accountSwitcherChevronBlock = Str::between(
        $css,
        '.fi-account-switcher-account-chevron {',
        '.fi-account-switcher-account-chevron .fi-icon {',
    );
    expect($block)
        ->toContain("width: {$expectedSize};")
        ->toContain("height: {$expectedSize};")
        ->toContain('border-left: 1px solid var(--color-gray-100);')
        ->toContain('margin-inline-end: calc(-1rem + 1px);')
        ->and($triggerBlock)
        ->toContain('size-10')
        ->toContain('rounded-lg')
        ->toContain('hover:bg-gray-100')
        ->toContain('dark:hover:bg-slate-700/60')
        ->not->toContain('size-full')
        ->not->toContain('rounded-none')
        ->and($notificationsWrapBlock)
        ->toContain('relative')
        ->and($itemBadgeBlock)
        ->toContain('absolute')
        ->toContain('left-4')
        ->and($profilePreviewBlock)
        ->toContain('items-center')
        ->and($profileAvatarBlock)
        ->toContain('justify-center')
        ->and($profileAvatarSizeBlock)
        ->toContain('size-16')
        ->and($accountSwitcherHeadingBlock)
        ->toContain('px-1')
        ->toContain('text-left')
        ->toContain('text-xs')
        ->and($accountSwitcherExpandedBlock)
        ->toContain('position: absolute;')
        ->toContain('bottom: 0;')
        ->toContain('max-height: min(21rem, calc(100dvh - 6rem));')
        ->toContain('overflow: hidden;')
        ->and($accountSwitcherExpandedListBlock)
        ->toContain('display: flex;')
        ->toContain('flex-direction: column;')
        ->toContain('gap: 0.125rem;')
        ->toContain('padding: 0.25rem;')
        ->and($accountSwitcherMobileBlock)
        ->toContain('max-height: min(21rem, calc(100dvh - 6rem));')
        ->and($accountSwitcherCtaBlock)
        ->toContain('padding: 0.25rem;')
        ->toContain('border-top: 1px solid var(--color-gray-100);')
        ->and($accountSwitcherFadeBlock)
        ->toContain('-webkit-mask-mode: alpha;')
        ->toContain('mask-mode: alpha;')
        ->toContain('rgb(0 0 0 / 1) 0% 35%')
        ->toContain('rgb(0 0 0 / 0.35) 60%')
        ->toContain('transparent 75% 100%')
        ->and($accountSwitcherSectionBlock)
        ->toContain('border: 1px solid var(--color-gray-100);')
        ->toContain('border-radius: var(--radius-lg, 0.5rem);')
        ->and($accountSwitcherAccountBlock)
        ->toContain('rounded-md')
        ->toContain('transition-colors')
        ->and($css)
        ->toContain('.tido-single-line-text {')
        ->toContain('transform: translateX(0);')
        ->toContain('transition: transform 220ms ease-out;')
        ->toContain('--tido-single-line-text-overflow')
        ->toContain('transition-delay: 300ms;')
        ->toContain('transition-duration: 0.75s;')
        ->toContain('.tido-single-line-text-clip {')
        ->toContain('mask-image: linear-gradient(')
        ->toContain('.tido-single-line-text-clip:hover .tido-single-line-text {')
        ->toContain('transform: translateX(')
        ->and($accountSwitcherChevronBlock)
        ->toContain('ml-auto')
        ->toContain('size-6')
        ->toContain('rounded-md');

    expect($css)
        ->toContain('.fi-account-switcher-account:hover {')
        ->toContain('background-color: var(--gray-50);')
        ->toContain('.dark .fi-account-switcher-section {')
        ->toContain('var(--color-slate-700) 60%');
});
