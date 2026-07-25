<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
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

test('profile section nav lists main column sections as anchor tabs', function () {
    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('tido-profile-section-nav', false)
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

    expect($html)->toContain('tido-profile-section-nav')
        ->and($html)->not->toContain('href="#profile-photo"')
        ->and($html)->not->toContain('href="#personal-details"');

    preg_match(
        '/<div[^>]*class="[^"]*tido-profile-section-nav[^"]*"[^>]*>.*?<\/div>\s*<\/div>/s',
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
        ->assertSee('tido-profile-section-nav__fade--left', false)
        ->assertSee('tido-profile-section-nav__fade--right', false)
        ->assertSee('tido-profile-section-nav--can-scroll-left', false)
        ->assertSee('tido-profile-section-nav--can-scroll-right', false)
        ->assertSee('scrollActiveTabIntoView', false);
});
