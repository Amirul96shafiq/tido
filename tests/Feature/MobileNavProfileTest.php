<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile personalize appearance section renders mobile navigation menu field', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('APPEARANCE', false)
        ->assertSee('Mobile Navigation Menu', false)
        ->assertSee('Save changes needed to take effect.', false)
        ->assertSee('Enabled: Bottom Bar', false)
        ->assertSee('Disabled: Top Bar', false)
        ->assertSee('tido-mobilenav-preview', false)
        ->assertSee('tido-mobilenav-preview-frame', false)
        ->assertSee('tido-mobile-preview-chrome', false)
        ->assertSee('tido-mobile-preview-topbar', false)
        ->assertSee('tido-mobilenav-preview-bar', false)
        ->assertSee('data.mobile_nav_enabled', false)
        ->assertSee("mobileNav ? 'Enabled: Bottom Bar' : 'Disabled: Top Bar'", false)
        ->assertSee('x-show="! mobileNav"', false)
        ->assertSee('x-show="mobileNav"', false)
        ->assertSet('data.mobile_nav_enabled', false);

    $mobileNavField = (string) file_get_contents(
        resource_path('views/filament/schemas/components/mobile-nav-field.blade.php'),
    );

    expect($mobileNavField)
        ->toContain('tido-mobilenav-preview-frame')
        ->toContain('mobile-preview-chrome')
        ->not->toContain('panel-preview-chrome')
        ->not->toContain('aspect-ratio: 1919 / 1079');
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
        ->and($notification->data['body'])->toContain('Mobile Navigation Menu');
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

test('database notifications livewire mounts at panel body end not the topbar', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('data-fi-modal-id="database-notifications"', false)
        ->assertSee('fi-topbar-database-notifications-trigger-sync', false)
        ->assertSee('panel-database-notifications', false);

    $topbar = (string) file_get_contents(
        resource_path('views/vendor/filament-panels/livewire/topbar.blade.php'),
    );

    expect($topbar)->not->toContain('getDatabaseNotificationsLivewireComponent');
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
        ->toContain("'mobilenav' => 28")
        ->toContain('$dropdownShift = $anchor === \'mobilenav\'')
        ->toContain("'mobilenav' => 16")
        ->toContain("in_array(\$anchor, ['topbar', 'mobilenav'], true)")
        ->toContain(':useModalTransition="$anchor === \'mobilenav\'"')
        ->toContain("key('account-switcher-'.\$userMenuInstanceKey)")
        ->toContain('fi-user-menu--')
        ->toContain('tido-user-menu-overlay')
        ->toContain('fi-dropdown-panel')
        ->toContain('attributeFilter: [\'style\', \'class\']')
        ->toContain('Heroicon::OutlinedUser')
        ->toContain('class="tido-mobilenav-label"')
        ->toContain('Profile</span>');
});

test('family member mobile nav add sheet disables budget recurring and settings create links', function (): void {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $user = User::query()->where('family_member_id', $member->id)->first();

    expect($user)->not->toBeNull();

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('Add Receipt', false)
        ->assertSee('Add Budget', false)
        ->assertSee('Add Recurring', false)
        ->assertSee('Settings', false)
        ->assertSee('Add Labels', false)
        ->assertSee('Add Payment Methods', false)
        ->assertSee('Add Family Members', false)
        ->assertSee(HouseholdAccess::createDeniedMessage(), false)
        ->assertDontSee(LabelResource::getUrl('create'), false)
        ->assertDontSee(PaymentMethodResource::getUrl('create'), false)
        ->assertDontSee(FamilyMemberResource::getUrl('create'), false);
});

test('primary mobile nav add sheet includes settings create links', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('Settings', false)
        ->assertSee('Add Labels', false)
        ->assertSee('Add Payment Methods', false)
        ->assertSee('Add Family Members', false)
        ->assertSee(LabelResource::getUrl('create'), false)
        ->assertSee(PaymentMethodResource::getUrl('create'), false)
        ->assertSee(FamilyMemberResource::getUrl('create'), false)
        ->assertSee('tido-text-marquee-clip', false)
        ->assertSee('tido-text-marquee-track', false)
        ->assertSee('x-ref="marqueeSegment"', false)
        ->assertSee('x-ref="marqueeTrack"', false);

    $blade = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );

    expect($blade)
        ->toContain('Heroicon::OutlinedTag')
        ->toContain('Heroicon::OutlinedCreditCard')
        ->toContain('Heroicon::OutlinedUserGroup')
        ->toContain('canCreateSettings')
        ->toContain('x-tido.text-marquee')
        ->toContain('text-class="inline-block whitespace-nowrap"');
});

test('mobile nav css hides topbar and offsets sticky chrome on small screens', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('html.tido-mobilenav')
        ->toContain('--tido-mobilenav-height: calc(')
        ->toContain('4.25rem + 1px + env(safe-area-inset-bottom, 0px)')
        ->toContain('html.tido-mobilenav .fi-topbar-ctn')
        ->toContain('.tido-mobilenav-root')
        ->toContain('html.tido-mobilenav .tido-mobilenav-root')
        ->not->toContain(".tido-mobilenav {\n    display: none;\n}")
        ->toContain('display: none !important')
        ->toContain('tido-sticky-marker--bottom')
        ->toContain("has(.tido-sticky-marker--bottom) {\n        bottom: 0.25rem;")
        ->toContain('inset: auto 0 var(--tido-mobilenav-height, 4rem) 0;')
        ->toContain('var(--tido-mobilenav-height, 4rem) + 0.25rem')
        ->toContain('html.tido-mobilenav body')
        ->toContain('overflow: hidden')
        ->toContain('html.tido-mobilenav .fi-body::after')
        ->toContain('inset-block-end: var(--tido-mobilenav-height, 4rem)')
        ->toContain('height: calc(100dvh - var(--tido-mobilenav-height, 4rem))')
        ->toContain('overflow-y: auto')
        ->toContain('padding-bottom: 0.25rem')
        ->toContain('html.tido-mobilenav .fi-sidebar')
        ->toContain('inset-block-end: var(--tido-mobilenav-height, 4rem)')
        ->toContain('html.tido-mobilenav .fi-ta-ctn')
        ->toContain('100vh - 16rem - var(--tido-mobilenav-height, 4rem)')
        ->toContain('html.tido-mobilenav .fi-ta-content-ctn')
        ->toContain(
            'max-height: calc(65vh - var(--tido-mobilenav-height, 4rem))',
        )
        ->toContain('--tido-mobilenav-inset')
        ->toContain('--tido-mobilenav-menu-gap')
        ->toContain('.tido-mobilenav-add-sheet')
        ->toContain('var(--tido-mobilenav-menu-gap, 2rem)')
        ->toContain('.tido-mobilenav-item--avatar')
        ->toContain('--sidebar-width: var(--tido-mobilenav-sidebar-width, 50vw)')
        ->toContain("html.tido-mobilenav .fi-sidebar {\n        inset-block-end: var(--tido-mobilenav-height, 4rem);\n        height: auto;\n        width: var(--tido-mobilenav-sidebar-width, 50vw) !important;\n        max-width: var(--tido-mobilenav-sidebar-width, 50vw);");

    $collapseFooterHide = Str::between(
        $css,
        'html.tido-mobilenav .fi-sidebar .fi-sidebar-collapse-footer,',
        'html.tido-mobilenav',
    );

    expect($collapseFooterHide)
        ->toContain('.fi-sidebar-close-collapse-sidebar-btn')
        ->toContain('.fi-sidebar-open-collapse-sidebar-btn')
        ->toContain('display: none !important');
});

test('mobile nav chrome sheets use the same modal enter transition as global search', function (): void {
    $dropdown = (string) file_get_contents(
        resource_path('views/vendor/filament/components/dropdown/index.blade.php'),
    );
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($dropdown)
        ->toContain("'useModalTransition' => false")
        ->toContain('x-transition:enter="fi-transition-enter"')
        ->toContain('x-transition:enter-start="fi-transition-enter-start"')
        ->toContain('x-transition:enter-end="fi-transition-enter-end"');

    expect($mobileNav)
        ->toContain('tido-mobilenav-add-sheet')
        ->toContain('x-transition:enter="fi-transition-enter"')
        ->toContain('x-transition:leave-end="fi-transition-leave-end"')
        ->toContain('$store.tidoMobileChrome.addOpen')
        ->not->toContain('tido-mobilenav-add-backdrop')
        ->not->toContain('transition ease-out duration-200')
        ->not->toContain('transition ease-in duration-150');

    expect($css)
        ->toContain('.fi-user-menu--mobilenav')
        ->toContain('.fi-dropdown-panel.fi-transition-enter,')
        ->toContain('.tido-mobilenav-add-sheet.fi-transition-enter,')
        ->toContain('.fi-dropdown-panel.fi-transition-enter-start,')
        ->toContain('.tido-mobilenav-add-sheet.fi-transition-enter-start,')
        ->toContain('@apply scale-95 opacity-0;')
        ->toContain('.fi-dropdown-panel.fi-transition-enter-end,')
        ->toContain('.tido-mobilenav-add-sheet.fi-transition-enter-end,')
        ->toContain('@apply scale-100 opacity-100;')
        ->toContain('html.tido-reduce-motion.tido-mobilenav')
        ->toContain('.tido-mobilenav-add-sheet.fi-transition-leave-end');
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
        ->toContain('@apply pointer-events-none absolute top-0 right-0 z-10 translate-x-2 -translate-y-1');
});

test('mobile nav bar light chrome uses solid white without backdrop blur', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $lightBarStart = strpos($css, 'html.tido-mobilenav .tido-mobilenav-bar {');
    $darkBarStart = strpos($css, 'html.dark.tido-mobilenav .tido-mobilenav-bar {');

    expect($lightBarStart)->not->toBeFalse()
        ->and($darkBarStart)->not->toBeFalse();

    $lightBarBlock = substr($css, $lightBarStart, $darkBarStart - $lightBarStart);

    expect($lightBarBlock)
        ->toContain('background-color: var(--color-white)')
        ->toContain('backdrop-filter: none')
        ->not->toContain('color-mix(')
        ->not->toContain('blur(12px)');
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
        ->toContain('tidoMobileChrome')
        ->toContain('mobilenavActive')
        ->toContain('isMobilenavActiveNow')
        ->toContain('isMobilenavViewport')
        ->toContain('syncMobileNavChromeStore')
        ->toContain('syncOverlay')
        ->toContain('overlayVisible')
        ->toContain('dismissOverlay')
        ->toContain('_syncOverlayShown')
        ->toContain('overlayShown')
        ->toContain('primeOverlay')
        ->toContain('_armSwapLock')
        ->toContain('_swapLocked')
        ->toContain('isChromeOpen')
        ->toContain('isAddSheetOpen')
        ->toContain('isSidebarOpen')
        ->toContain('PanelsRenderHook::BODY_END')
        ->not->toContain('PanelsRenderHook::LAYOUT_START')
        ->toContain('livewire:navigated')
        ->toContain('livewire:navigating');
});

test('mobile nav closes global search when another chrome slot is activated', function (): void {
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );

    expect($mobileNav)
        ->toContain('searchModal()')
        ->toContain('searchModalData()')
        ->toContain('isSearchOpen()')
        ->toContain('closeSearch()')
        ->toContain('toggleSearch()')
        ->toContain("getElementById('global-search-modal::plugin')")
        ->toContain('Alpine.$data(modal)')
        ->toContain('closeSearchModal()')
        ->toContain('searchOpen = true')
        ->toContain('if (this.isSearchOpen()) {')
        ->toContain('this.closeSearch();')
        ->toContain('x-on:click="closeSearch()"')
        ->toContain('x-bind:aria-expanded="isSearchOpen()"');
});

test('mobile nav bottom bar uses active-state icons for home menu and add slots', function (): void {
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );

    expect($mobileNav)
        ->toContain('wire:current.exact="tido-mobilenav-item--current"')
        ->toContain('Heroicon::OutlinedHome')
        ->toContain('Heroicon::Home')
        ->toContain('tido-mobilenav-icon--outline')
        ->toContain('tido-mobilenav-icon--solid')
        ->toContain('Heroicon::OutlinedBars3')
        ->toContain('Heroicon::OutlinedBars3BottomLeft')
        ->toContain('x-show="! $store.sidebar.isOpen"')
        ->toContain('tido-mobilenav-add-svg--default')
        ->toContain('tido-mobilenav-add-svg--active')
        ->toContain('tido-mobilenav-add-eye--right')
        ->toContain('tido-mobilenav-add-eye--left')
        ->toContain('tido-mobilenav-add-eye-sclera')
        ->toContain('tido-mobilenav-add-eye-pupil')
        ->toContain('tido-mobilenav-add-bg')
        ->toContain('tido-mobilenav-add-border')
        ->toContain('tido-mobilenav-add-btn')
        ->toContain('tido-mobilenav-add-btn--open')
        ->toContain('tido-mobilenav-add-svg')
        ->toContain('tido-mobilenav-add-zzz')
        ->toContain('tido-mobilenav-add-z--1')
        ->toContain('tido-mobilenav-add-z--2')
        ->toContain('$store.tidoMobileChrome.addOpen ? closeAdd() : openAdd()')
        ->toContain('Home</span>')
        ->toContain('Menu</span>')
        ->toContain('Add</span>')
        ->toContain('Search</span>')
        ->toContain("x-bind:class=\"{ 'tido-mobilenav-item--active text-primary-600 dark:text-primary-400': \$store.sidebar.isOpen }\"")
        ->toContain("x-bind:class=\"{ 'tido-mobilenav-item--active text-primary-600 dark:text-primary-400': isSearchOpen() }\"");
});

test('mobile nav add sheet sits flush on the bottom bar', function (): void {
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );

    expect($mobileNav)
        ->toContain('tido-mobilenav-add-card')
        ->toContain('rounded-xl')
        ->toContain('x-transition:enter="fi-transition-enter"')
        ->not->toContain('border-b-0')
        ->not->toContain('bottom-[var(--tido-mobilenav-height');
});

test('mobile chrome overlays match the sidebar close overlay', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );
    $overlay = (string) file_get_contents(
        resource_path('views/components/tido/mobile-chrome-overlay.blade.php'),
    );
    $userMenu = (string) file_get_contents(
        resource_path('views/vendor/filament-panels/components/user-menu.blade.php'),
    );
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($css)
        ->toContain('.tido-chrome-overlay {')
        ->toContain('@apply fixed inset-0 z-30 bg-gray-950/50 transition-opacity duration-300 dark:bg-gray-950/75')
        ->toContain('.fi-sidebar-close-overlay {')
        ->toContain('[id="global-search-modal::plugin"].fi-modal > .fi-modal-close-overlay')
        ->toContain('html.tido-mobilenav')
        ->toContain('.tido-mobilenav-shared-chrome-overlay')
        ->toContain('.fi-sidebar-close-overlay:not(.tido-mobilenav-shared-chrome-overlay)')
        ->toContain('display: none !important')
        ->toContain('backdrop-filter: none !important')
        ->toContain('.fi-layout:has(.fi-modal.fi-modal-open')
        ->toContain('[id="global-search-modal::plugin"].fi-modal.fi-modal-open')
        ->toContain('[data-fi-modal-id="changelog"].fi-modal.fi-modal-open')
        ->toContain('[data-fi-modal-id="database-notifications"].fi-modal.fi-modal-open')
        ->toContain('z-index: calc(var(--tido-mobilenav-z-chrome, 65) + 1)')
        ->toContain('> .fi-modal-window-ctn {')
        ->toContain('bottom: var(--tido-mobilenav-height, 4rem)')
        ->toContain('--tido-mobilenav-z-chrome: 65')
        ->toContain('z-index: var(--tido-mobilenav-z-chrome, 65)')
        ->not->toContain('html.tido-mobilenav .tido-mobilenav-root > .tido-chrome-overlay')
        ->toContain('.tido-user-menu-overlay {');

    $chromeOverlay = Str::between(
        $css,
        ' * See docs/ui-mobile-nav.md and docs/ui-modal-overlay.md.
 */
.tido-chrome-overlay {',
        '.fi-sidebar-close-overlay {',
    );

    expect($chromeOverlay)
        ->toContain('backdrop-filter: none')
        ->toContain('transition-opacity duration-300')
        ->not->toContain('@apply backdrop-blur-md');

    $sharedChromeOverlay = Str::between(
        $css,
        'html.tido-mobilenav .tido-mobilenav-shared-chrome-overlay {',
        '.fi-sidebar-close-overlay {',
    );

    expect($css)
        ->toContain('html.tido-mobilenav .tido-mobilenav-shared-chrome-overlay')
        ->toContain('z-index: 29 !important')
        ->toContain('@media (min-width: 1024px)')
        ->toContain('html.tido-mobilenav .tido-mobilenav-shared-chrome-overlay')
        ->toContain('pointer-events: none !important');

    expect($sharedChromeOverlay)
        ->not->toContain('transition: none !important');

    expect($mobileNav)
        ->toContain('$store.tidoMobileChrome')
        ->toContain('syncOverlay()')
        ->toContain('dismissOverlay()')
        ->toContain('this.$store.tidoMobileChrome.primeOverlay()')
        ->not->toContain('x-on:click.capture="$store.tidoMobileChrome.primeOverlay()"')
        ->not->toContain('tido-mobilenav-add-backdrop')
        ->not->toContain('tido-user-menu-overlay')
        ->not->toContain('backdrop-blur-md');

    expect($overlay)
        ->toContain('tido-mobilenav-shared-chrome-overlay')
        ->toContain('tido-chrome-overlay')
        ->toContain('fi-sidebar-close-overlay')
        ->toContain('x-effect')
        ->toContain('mobilenavActive')
        ->toContain('$store.tidoMobileChrome?.overlayShown')
        ->toContain("classList.toggle('opacity-0'")
        ->toContain("classList.toggle('pointer-events-none'")
        ->toContain('closeActiveChrome()')
        ->not->toContain('x-transition.opacity.300ms');

    $panelBodyEnd = (string) file_get_contents(
        resource_path('views/components/panel-body-end.blade.php'),
    );

    expect($panelBodyEnd)
        ->toContain('tido.mobile-chrome-overlay')
        ->toContain('tido.mobile_nav')
        ->toContain("key('panel-database-notifications')")
        ->toContain("'lazy' => false");

    expect($provider)
        ->not->toContain('PanelsRenderHook::LAYOUT_START');

    expect($userMenu)
        ->toContain("\$anchor !== 'mobilenav'")
        ->toContain('! $store.tidoNotifications?.menuOpen && $store.tidoMobileChrome?.primeOverlay()')
        ->toContain('x-transition.duration.300ms.opacity')
        ->toContain('tido-chrome-overlay tido-user-menu-overlay lg:hidden');
});

test('mobile sidebar expands all navigation groups when menu opens', function (): void {
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );
    $sidebar = (string) file_get_contents(
        resource_path('views/vendor/filament-panels/livewire/sidebar.blade.php'),
    );
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $vite = (string) file_get_contents(base_path('vite.config.js'));
    $docs = (string) file_get_contents(base_path('docs/ui-mobile-nav.md'));

    expect($mobileNav)
        ->toContain('expandAllSidebarGroups()')
        ->toContain('this.$store.sidebar.collapsedGroups = []')
        ->toContain('this.expandAllSidebarGroups()');

    expect($sidebar)
        ->toContain('window.innerWidth < 1024')
        ->toContain("group.classList.remove('fi-collapsed')")
        ->not->toContain('tidoMobileOpenNavGroup');

    expect($provider)->not->toContain('sidebar-group-accordion');
    expect($vite)->not->toContain('sidebar-group-accordion.js');

    expect($docs)
        ->toContain('start fully expanded when the Menu drawer opens')
        ->not->toContain('sidebar-group-accordion.js');
});

test('mobile nav closes sidebar and add sheet when user menu opens', function (): void {
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );

    expect($mobileNav)
        ->toContain('$watch(\'$store.tidoNotifications.menuOpen\'')
        ->toContain('tidoMobileChrome.addOpen = false')
        ->toContain('this.$store.sidebar.close()');
});

test('mobile nav css swaps home outline and solid icons on current dashboard link', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('html.tido-mobilenav .tido-mobilenav-icon--solid')
        ->toContain('.tido-mobilenav-item--current')
        ->toContain('.tido-mobilenav-icon--outline')
        ->toContain('.tido-mobilenav-item[data-current]')
        ->toContain('.tido-mobilenav-icon--solid');
});

test('mobile nav default add svg runs looping breathing animation with active state suppression', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('@keyframes tido-mobilenav-add-breathe')
        ->toContain('@keyframes tido-mobilenav-z1-float')
        ->toContain('@keyframes tido-mobilenav-z2-float')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-svg--default')
        ->toContain('animation: tido-mobilenav-add-breathe 4s ease-in-out infinite;')
        ->toContain('transform-origin: center bottom;')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-z')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-z--1')
        ->toContain('animation: tido-mobilenav-z1-float 4s ease-in-out infinite;')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-z--2')
        ->toContain('animation: tido-mobilenav-z2-float 4s ease-in-out infinite;')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-btn--open .tido-mobilenav-add-svg')
        ->toContain('html.tido-mobilenav .tido-mobilenav-item--active .tido-mobilenav-add-svg')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-svg--active')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-btn--open .tido-mobilenav-add-z')
        ->toContain('html.tido-mobilenav .tido-mobilenav-item--active .tido-mobilenav-add-z')
        ->toContain('animation: none !important;')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-btn:active .tido-mobilenav-add-svg')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-btn:active .tido-mobilenav-add-z')
        ->toContain('animation-play-state: paused;');
});

test('mobile nav active add svg runs blinking eye animation', function (): void {
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($mobileNav)
        ->toContain('scheduleBlink')
        ->toContain('blinkCount = Math.floor(Math.random() * 3) + 1')
        ->toContain("is-blinking-' + blinkCount");

    expect($css)
        ->toContain('@keyframes tido-mobilenav-blink')
        ->toMatch('/@keyframes tido-mobilenav-blink\s*\{[^}]*rotate\(\s*-?\d+(\.\d+)?deg\)/')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-svg--active .tido-mobilenav-add-eye')
        ->toContain('.is-blinking-1')
        ->toContain('.is-blinking-2')
        ->toContain('.is-blinking-3')
        ->toContain('transform-box: fill-box;')
        ->toContain('transform-origin: center center;')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-btn:active .tido-mobilenav-add-eye')
        ->toContain('animation-play-state: paused;');
});

test('mobile nav add svgs apply 1px top border behind mobile nav bar matching mobile nav border in light and dark themes', function (): void {
    $mobileNav = (string) file_get_contents(
        resource_path('views/filament/livewire/mobile-nav.blade.php'),
    );
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($mobileNav)
        ->toContain('tido-mobilenav-add-border-behind')
        ->toContain('tido-mobilenav-add-border')
        ->toContain('tido-mobilenav-add-bg');

    expect($css)
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-border-behind {')
        ->toContain('z-index: calc(var(--tido-mobilenav-z-chrome, 65) - 1);')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-border {')
        ->toContain('stroke: var(--color-gray-100);')
        ->toContain('stroke-width: 8px;')
        ->toContain('html.dark.tido-mobilenav .tido-mobilenav-add-border {')
        ->toContain('var(--color-slate-700) 60%')
        ->toContain('html.tido-mobilenav .tido-mobilenav-add-bg {')
        ->toContain('stroke-width: 6px;');
});
