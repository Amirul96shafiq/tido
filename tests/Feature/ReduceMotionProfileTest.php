<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile personalize preferences section renders reduce motion toggle', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(EditProfile::class)
        ->assertSee('PREFERENCES', false)
        ->assertSee('Reduce Motion', false)
        ->assertSee('Disable count-up, marquee, and other decorative animation. Save to keep this preference for future sign-ins.', false)
        ->assertSee('fi-profile-toggle-field', false)
        ->assertSchemaComponentExists('personalize-preferences')
        ->assertSet('data.reduce_motion', false);

    $fieldset = $component->instance()->form->getComponent('personalize-preferences');
    $toggle = $component->instance()->form->getComponent('reduce_motion');

    expect($fieldset?->getColumns('lg'))->toBe(1)
        ->and($toggle?->getColumnSpan('default'))->toBe('full');
});

test('profile saves the reduce motion preference', function (bool $enabled): void {
    $user = User::factory()->create([
        'reduce_motion' => ! $enabled,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.reduce_motion', $enabled)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->reduce_motion)->toBe($enabled);
})->with([
    'enabled' => true,
    'disabled' => false,
]);

test('changing the reduce motion preference reports the profile change', function (): void {
    $user = User::factory()->create([
        'reduce_motion' => false,
        'notify_profile_updates' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.reduce_motion', true)
        ->call('save')
        ->assertHasNoErrors();

    $notification = $user->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['body'])->toContain('Reduce Motion');
});

test('admin panel injects reduce motion script and class when preference is enabled', function (): void {
    $user = User::factory()->create([
        'reduce_motion' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('tidoPrefersReducedMotion', false)
        ->assertSee('tidoSetReduceMotion', false)
        ->assertSee("document.documentElement.classList.add('tido-reduce-motion')", false);
});

test('admin panel does not inject reduce motion class when preference is disabled', function (): void {
    $user = User::factory()->create([
        'reduce_motion' => false,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('tidoPrefersReducedMotion', false)
        ->assertDontSee("document.documentElement.classList.add('tido-reduce-motion')", false);
});

test('admin panel reduce motion script syncs preference across spa navigation', function (): void {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)
        ->toContain('sessionStorage.setItem')
        ->toContain('tidoReduceMotion')
        ->toContain('livewire:navigated')
        ->toContain('livewire:navigating')
        ->toContain('parseCountUpConfig')
        ->toContain('tido-reduce-motion-changed')
        ->toContain('snapCountUpsToFinal')
        ->toContain('syncMarqueeMotion')
        ->toContain('scheduleMarqueeSync');
});

test('reduce motion css disables ping pulse animations', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-ping-pulse')
        ->toContain('@keyframes tido-ping-pulse')
        ->toContain('html.tido-reduce-motion .tido-ping-pulse')
        ->toContain('opacity: 0 !important;');
});

test('reduce motion css disables sidebar collapsed labels and collapse cta motion', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('html.tido-reduce-motion .fi-sidebar-group-collapsed-label')
        ->toContain('html.tido-reduce-motion .fi-sidebar-collapse-morph')
        ->toContain('html.tido-reduce-motion .fi-sidebar-close-collapse-sidebar-btn')
        ->toContain('html.tido-reduce-motion .fi-sidebar-collapse-toggle-label')
        ->toContain('html.tido-reduce-motion .fi-sidebar-group-items')
        ->toContain('html.tido-reduce-motion .fi-sidebar .fi-dropdown-panel')
        ->toContain('html.tido-reduce-motion.tido-mobilenav')
        ->toContain('.fi-user-menu--mobilenav')
        ->toContain('.fi-dropdown-panel.fi-transition-enter-start,')
        ->toContain('.tido-mobilenav-add-sheet.fi-transition-enter-start,')
        ->toContain('html.tido-reduce-motion .tido-chrome-overlay')
        ->toContain('html.tido-reduce-motion .tido-sidebar-flyout-panel')
        ->toContain('.fi-sidebar.fi-sidebar-animating .fi-sidebar-group-collapsed-label')
        ->toContain('.fi-sidebar.fi-sidebar-animating .fi-sidebar-collapse-toggle-label')
        ->toContain('html.tido-reduce-motion.tido-mobilenav .tido-mobilenav-add-svg')
        ->toContain('html.tido-reduce-motion.tido-mobilenav .tido-mobilenav-add-z');
});
