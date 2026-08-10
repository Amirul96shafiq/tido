<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('profile page renders top and bottom sticky blur markers', function () {
    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false);
});

test('active sessions table header stays below sticky profile tabs', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    preg_match(
        '/\.fi-profile-page\s+#active-sessions\s+\.fi-ta-table\s*>\s*thead\s*\{(?P<declarations>.*?)\}/s',
        $css,
        $matches,
    );

    expect($matches['declarations'] ?? null)->toContain('z-index: 5;');
});

test('profile section nav lists main column sections as anchor tabs', function () {
    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('tido-section-nav', false)
        ->assertSee('Personalize')
        ->assertSee('Account &amp; Security', false)
        ->assertSee('Active Sessions')
        ->assertSee('Regional Preferences')
        ->assertSee('Notifications')
        ->assertSee('Danger Zone')
        ->assertSee('#personalize', false)
        ->assertSee('#account-security', false)
        ->assertSee('#active-sessions', false)
        ->assertSee('#regional-preferences', false)
        ->assertSee('#notifications', false)
        ->assertSee('#danger-zone', false);
});

test('profile section nav excludes sidebar photo and personal details', function () {
    $response = Livewire::test(EditProfile::class)
        ->assertSuccessful();

    $html = $response->html();

    expect($html)->toContain('tido-section-nav')
        ->and($html)->not->toContain('href="#profile-photo"')
        ->and($html)->not->toContain('href="#personal-details"');

    preg_match(
        '/<div[^>]*class="[^"]*tido-section-nav[^"]*"[^>]*>.*?<\/div>\s*<\/div>/s',
        $html,
        $navMatch,
    );

    if (isset($navMatch[0])) {
        expect($navMatch[0])->not->toContain('Profile Photo')
            ->and($navMatch[0])->not->toContain('Personal Details');
    }
});

test('profile section nav items match sectionNavItems helper', function () {
    expect(EditProfile::sectionNavItems())->toBe([
        ['label' => 'Personalize', 'id' => 'personalize'],
        ['label' => 'Account & Security', 'id' => 'account-security'],
        ['label' => 'Active Sessions', 'id' => 'active-sessions'],
        ['label' => 'Regional Preferences', 'id' => 'regional-preferences'],
        ['label' => 'Notifications', 'id' => 'notifications'],
        ['label' => 'Danger Zone', 'id' => 'danger-zone'],
    ]);
});

test('family member profile hides account and security section and nav', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60115554444',
    ]);
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user);

    expect(EditProfile::sectionNavItems())->toBe([
        ['label' => 'Personalize', 'id' => 'personalize'],
        ['label' => 'Active Sessions', 'id' => 'active-sessions'],
        ['label' => 'Regional Preferences', 'id' => 'regional-preferences'],
        ['label' => 'Notifications', 'id' => 'notifications'],
    ]);

    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertDontSee('Account &amp; Security', false)
        ->assertDontSee('#account-security', false)
        ->assertDontSee('Change Password')
        ->assertDontSee('Danger Zone');
});

test('profile section nav smooth scrolls on tab click', function () {
    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false)
        ->assertSee('x-on:click.capture', false);
});

test('profile section nav exposes horizontal scroll hint affordances', function () {
    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('updateScrollHints', false)
        ->assertSee('canScrollLeft', false)
        ->assertSee('canScrollRight', false)
        ->assertSee('tido-section-nav__fade--left', false)
        ->assertSee('tido-section-nav__fade--right', false)
        ->assertSee('tido-section-nav--can-scroll-left', false)
        ->assertSee('tido-section-nav--can-scroll-right', false)
        ->assertSee('scrollActiveTabIntoView', false)
        ->assertSee('resetTabsScrollAtPageTop', false);
});

test('profile section nav tracks the section at the visible scroll boundary', function () {
    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('syncActiveSection', false)
        ->assertSee('scheduleActiveSectionSync', false)
        ->assertSee('getBoundingClientRect()', false)
        ->assertSee('window.requestAnimationFrame', false)
        ->assertSee('x-on:scroll.window.passive="scheduleActiveSectionSync()"', false)
        ->assertSee('x-on:resize.window.passive="scheduleActiveSectionSync()"', false)
        ->assertDontSee('IntersectionObserver', false)
        ->assertDontSee("rootMargin: '-30% 0px -55% 0px'", false);
});

test('profile section nav supports click drag horizontal scroll', function () {
    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('isDragging', false)
        ->assertSee('dragMoved', false)
        ->assertSee('onTabPointerDown', false)
        ->assertSee('onTabPointerMove', false)
        ->assertSee('endTabDrag', false)
        ->assertSee('setPointerCapture', false)
        ->assertSee('tido-section-nav--dragging', false)
        ->assertSee("dragstart', (event) => event.preventDefault()", false)
        ->assertSee('draggable="false"', false);
});

test('profile section nav leaves touch scrolling to the browser', function () {
    $html = Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->html();
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($html)
        ->toContain("const isTouchPointer = event.pointerType === 'touch';")
        ->toContain('if (! isTouchPointer && tabs.hasPointerCapture?.(event.pointerId) !== true)')
        ->toContain('if (isTouchPointer)')
        ->and($css)->toContain('touch-action: pan-x pan-y;');
});
