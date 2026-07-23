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
        ->assertSee('tido-sidebar-preview', false)
        ->assertSee('Expanded', false)
        ->assertSee('$store.sidebar.open()', false)
        ->assertSee('$store.sidebar.close()', false);
});

test('profile personalize section renders theme mode switcher', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('Theme Mode', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('theme = \'light\'', false)
        ->assertSee('theme = \'dark\'', false)
        ->assertSee('theme = \'system\'', false);
});

test('sidebar and theme preferences are not stored on users table', function (): void {
    expect(Schema::hasColumn('users', 'sidebar_collapsed'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'sidebar_mode'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'theme_mode'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'theme'))->toBeFalse();
});
