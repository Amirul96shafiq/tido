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

test('filament slide-overs use the shared custom scrollbar theme', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-modal-slide-over .fi-modal-window-ctn > .fi-modal-window,')
        ->toContain('.fi-no-database .fi-modal-window-ctn > .fi-modal-window > .fi-modal-content,')
        ->toContain('.fi-modal-slide-over .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar,')
        ->toContain('> .fi-modal-content::-webkit-scrollbar,')
        ->toContain('.fi-modal-slide-over .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar-thumb,')
        ->toContain('> .fi-modal-content::-webkit-scrollbar-thumb,')
        ->not->toContain(".fi-modal[id='ollama-config-details'] .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar")
        ->not->toContain(".fi-modal[id='ollama-supported-tasks'] .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar")
        ->not->toContain('.fi-modal.fi-evolution-api-details .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar');

    $changelog = (string) file_get_contents(resource_path('views/components/changelog-modal.blade.php'));
    $ollama = (string) file_get_contents(resource_path('views/filament/pages/partials/ollama-content.blade.php'));
    $evolution = (string) file_get_contents(resource_path('views/filament/pages/partials/evolution-api-content.blade.php'));

    expect($changelog)->not->toContain('custom-scrollbar')
        ->and($ollama)->not->toContain('custom-scrollbar')
        ->and($evolution)->not->toContain('custom-scrollbar');
});

test('sidebar nav uses page-scroller webkit overlay instead of chromium scrollbar-width', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $chromiumWidthBlock = Str::between(
        $css,
        'Database notifications pin header/footer and scroll .fi-modal-content. */',
        '@supports not selector(::-webkit-scrollbar) {',
    );

    $firefoxBlock = Str::between(
        $css,
        '@supports not selector(::-webkit-scrollbar) {',
        'html::-webkit-scrollbar,',
    );

    $webkitWidthBlock = Str::between(
        $css,
        'html::-webkit-scrollbar,',
        '.fi-sidebar-nav::-webkit-scrollbar-button {',
    );

    $chromiumDarkColorBlock = Str::between(
        $css,
        '.fi-ta-content-ctn::-webkit-scrollbar-thumb:hover {',
        'html.dark::-webkit-scrollbar-thumb,',
    );

    expect($chromiumWidthBlock)
        ->toContain('scrollbar-width: thin !important;')
        ->not->toContain('.fi-sidebar-nav')
        ->and($firefoxBlock)
        ->toContain('.fi-sidebar-nav {')
        ->toContain('.dark .fi-sidebar-nav {')
        ->toContain('scrollbar-width: thin;')
        ->and($webkitWidthBlock)
        ->toContain('.fi-sidebar-nav::-webkit-scrollbar,')
        ->toContain('width: 6px !important;')
        ->and($chromiumDarkColorBlock)
        ->toContain('scrollbar-color: rgba(51, 65, 85, 0.5) transparent !important;')
        ->not->toContain('.fi-sidebar-nav')
        ->and($css)
        ->toContain('.fi-sidebar-nav::-webkit-scrollbar-button {');
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
