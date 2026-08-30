<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

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

test('filament nested panels use overlay scrollbar not webkit gutter', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-modal-slide-over .fi-modal-window-ctn > .fi-modal-window,')
        ->toContain('.fi-no-database .fi-modal-window-ctn > .fi-modal-window > .fi-modal-content,')
        ->toContain('.results-container,')
        ->not->toContain('.fi-modal-slide-over .fi-modal-window-ctn > .fi-modal-window::-webkit-scrollbar')
        ->not->toContain('.fi-dropdown-panel::-webkit-scrollbar')
        ->not->toContain('.fi-ta-filters-body::-webkit-scrollbar')
        ->not->toContain('.results-container::-webkit-scrollbar')
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

test('page chrome keeps webkit gutter and nested lists use overlay tint', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $firefoxBlock = Str::between(
        $css,
        '@supports not selector(::-webkit-scrollbar) {',
        'html::-webkit-scrollbar,',
    );

    $webkitWidthBlock = Str::between(
        $css,
        'html::-webkit-scrollbar,',
        'html::-webkit-scrollbar-track,',
    );

    $overlayBlock = Str::between(
        $css,
        'Hint the compositor so nested wheel animation can match the root scroller.',
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups {',
    );

    expect($webkitWidthBlock)
        ->toContain('body::-webkit-scrollbar,')
        ->toContain('.fi-main-ctn::-webkit-scrollbar')
        ->toContain('width: 6px !important;')
        ->not->toContain('.fi-dropdown-panel')
        ->not->toContain('.fi-ta-filters-body')
        ->not->toContain('.fi-modal-slide-over')
        ->not->toContain('.results-container')
        ->and($css)
        ->toContain('html::-webkit-scrollbar,')
        ->and($firefoxBlock)
        ->toContain('.fi-sidebar-nav,')
        ->toContain('.custom-scrollbar,')
        ->toContain('.fi-ta-content-ctn,')
        ->toContain('.fi-dropdown-panel,')
        ->toContain('.fi-ta-filters-body,')
        ->toContain('.fi-ta-col-manager-body,')
        ->toContain('.fi-modal-slide-over .fi-modal-window-ctn > .fi-modal-window,')
        ->toContain('.fi-modal:not(.fi-modal-slide-over) > .fi-modal-window-ctn,')
        ->toContain('.results-container,')
        ->toContain('.tido-date-picker-month-panel')
        ->toContain('scrollbar-width: thin;')
        ->toContain('var(--color-white)')
        ->toContain('var(--color-slate-800)')
        ->and($overlayBlock)
        ->toContain('will-change: scroll-position')
        ->toContain('.fi-dropdown-panel,')
        ->toContain('.tido-date-picker-month-panel')
        ->not->toContain('::-webkit-scrollbar')
        ->and($css)
        ->toContain('--tido-scrollbar-track: var(--tido-bg-color-light, var(--color-white));')
        ->toContain('--tido-scrollbar-track: var(--tido-bg-color-dark, var(--color-slate-800));')
        ->toContain('background-color: var(--tido-scrollbar-track) !important;')
        ->toContain('.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav {')
        ->toContain('scrollbar-width: none;')
        ->toContain('.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav::-webkit-scrollbar {')
        ->toContain('scrollbar-color: var(--tido-scrollbar-thumb) var(--color-white);')
        ->toContain('scrollbar-color: var(--tido-scrollbar-thumb) var(--color-slate-800);');
});

test('global search modal vendor view omits classic scrollbar utilities', function () {
    $blade = (string) file_get_contents(resource_path('views/vendor/global-search-modal/components/global-search-modal.blade.php'));

    expect($blade)
        ->toContain('results-container')
        ->not->toContain('[scrollbar-width:thin]')
        ->not->toContain('::-webkit-scrollbar]');
});

test('icon picker overflow grid uses custom scrollbar class', function () {
    $blade = (string) file_get_contents(resource_path('views/filament/forms/components/icon-picker.blade.php'));

    expect($blade)->toContain('custom-scrollbar grid max-h-96');
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
