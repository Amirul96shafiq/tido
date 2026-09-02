<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile personalize section renders live sidebar mode preview', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('Sidebar Mode', false)
        ->assertSee('Restricted to larger responsive users.', false)
        ->assertSee('tido-sidebar-preview', false)
        ->assertSee('tido-preview-static', false)
        ->assertSee('Expanded style', false)
        ->assertSee('collapsed ? \'Collapsed style\' : \'Expanded style\'', false)
        ->assertSee('tido_dark_logo', false)
        ->assertSee('tido_dark_logo_c', false)
        ->assertSee('isRestricted', false)
        ->assertSee('x-bind:disabled="isRestricted"', false)
        ->assertSee('$store.sidebar.open()', false)
        ->assertSee('$store.sidebar.close()', false)
        ->assertSee('data.stylized_background_enabled', false)
        ->assertSee('images/bg-l-v8.webp', false)
        ->assertSee('images/bg-d-v8.webp', false);
});

test('profile personalize section renders theme mode switcher', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('Theme Mode', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('theme-changed.window', false)
        ->assertSee('light: \'Light\'', false)
        ->assertSee('dark: \'Dark\'', false)
        ->assertSee('system: \'System\'', false)
        ->assertSee('theme = \'light\'', false)
        ->assertSee('theme = \'dark\'', false)
        ->assertSee('theme = \'system\'', false);
});

test('profile personalize section renders stylized background indicators', function (): void {
    $user = User::factory()->create([
        'stylized_background_enabled' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('Stylized Background', false)
        ->assertSee('Enabled: Stylized Mode', false)
        ->assertSee('Disabled: Focus Mode', false)
        ->assertSee('data.stylized_background_enabled', false)
        ->assertSee('$store.sidebar.isOpenDesktop', false)
        ->assertSee('tido-sidebar-preview-rail', false)
        ->assertSee('collapsed ? \'Collapsed style\' : \'Expanded style\'', false);
});

test('profile personalize section renders mobile navigation menu portrait preview', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('Mobile Navigation Menu', false)
        ->assertSee('Enabled: Bottom Bar', false)
        ->assertSee('tido-mobilenav-preview-frame', false)
        ->assertSee('tido-mobile-preview-chrome', false)
        ->assertSee('tido-mobilenav-preview-bar-grid', false)
        ->assertSee('tido-mobilenav-preview-slot--primary', false)
        ->assertSee('x-show="! mobileNav"', false)
        ->assertSee('x-show="mobileNav"', false);
});

test('desktop panel preview chrome does not render mobile navigation chrome', function (): void {
    $chrome = (string) file_get_contents(resource_path('views/components/tido/panel-preview-chrome.blade.php'));

    expect($chrome)
        ->not->toContain('mobileNav')
        ->not->toContain('tido-mobilenav-preview-bar')
        ->not->toContain('tido-mobile-preview-chrome');
});

test('sidebar and theme preferences are not stored on users table', function (): void {
    expect(Schema::hasColumn('users', 'sidebar_collapsed'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'sidebar_mode'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'theme_mode'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'theme'))->toBeFalse();
});
