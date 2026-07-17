<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);
});

test('user menu orders profile changelogs notifications and logout', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $items = Filament::getUserMenuItems();
    $keys = array_keys($items);

    expect($keys)->toBe(['profile', 'changelogs', 'notifications', 'logout']);

    expect($items['profile']->getIcon())->toBe('heroicon-o-user');
    expect($items['profile']->getSort())->toBeGreaterThanOrEqual(0);
    expect($items['changelogs']->getLabel())->toBe('Changelogs');
    expect($items['changelogs']->getSort())->toBeGreaterThan($items['profile']->getSort());
    expect($items['notifications']->getLabel())->toBe('Notifications');
    expect($items['notifications']->getIcon())->toBe('heroicon-o-bell');
    expect($items['notifications']->getSort())->toBeGreaterThan($items['changelogs']->getSort());
    expect($items['logout']->getSort())->toBeGreaterThan($items['notifications']->getSort());
    expect($items['logout']->getIcon())->toBe('heroicon-o-arrow-right-start-on-rectangle');
    expect($items['logout']->getColor())->toBe('danger');
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
    $response->assertSee('offset: -48', false);
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

    expect($block)
        ->toContain("width: {$expectedSize};")
        ->toContain("height: {$expectedSize};")
        ->toContain('border-left: 1px solid var(--color-gray-100);')
        ->toContain('margin-inline-end: -1rem;')
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
        ->toContain('left-7')
        ->and($profilePreviewBlock)
        ->toContain('items-center')
        ->and($profileAvatarBlock)
        ->toContain('justify-center')
        ->and($profileAvatarSizeBlock)
        ->toContain('size-12');
});
