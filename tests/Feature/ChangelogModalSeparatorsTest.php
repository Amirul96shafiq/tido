<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('changelog slide-over uses filament modal with visible dark theme separators', function () {
    $blade = (string) file_get_contents(resource_path('views/components/changelog-modal.blade.php'));

    expect($blade)
        ->toContain('id="changelog"')
        ->toContain('slide-over')
        ->toContain('class="fi-changelog"')
        ->toContain("open-modal', { detail: { id: 'changelog' }")
        ->toContain('border-b border-gray-200')
        ->toContain('dark:border-gray-700')
        ->not->toContain('dark:border-gray-800')
        ->not->toContain('z-[99999]');
});

test('changelog slide-over css fixes content bleed like database notifications', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-changelog > .fi-modal-close-overlay')
        ->toContain('.fi-changelog .fi-modal-window-ctn > .fi-modal-window .fi-modal-content');
});

test('changelog and database notification slide-overs use the shared custom scrollbar theme', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-changelog .fi-modal-window-ctn > .fi-modal-window,')
        ->toContain('.fi-no-database .fi-modal-window-ctn > .fi-modal-window > .fi-modal-content,')
        ->toContain('.fi-changelog .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar,')
        ->toContain('> .fi-modal-content::-webkit-scrollbar,')
        ->toContain('.fi-changelog .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar-thumb,')
        ->toContain('> .fi-modal-content::-webkit-scrollbar-thumb,');

    $blade = (string) file_get_contents(resource_path('views/components/changelog-modal.blade.php'));

    expect($blade)->not->toContain('custom-scrollbar');
});

test('dashboard renders changelog slide-over shell', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('data-fi-modal-id="changelog"', false)
        ->assertSee('fi-modal-slide-over', false);
});

test('user menu changelogs action opens changelog slide-over', function () {
    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee("\$dispatch('open-modal', { id: 'changelog' })", false);
});
