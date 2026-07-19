<?php

declare(strict_types=1);

test('topbar and sidebar header height match collapsed sidebar width', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $expectedHeight = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';

    $topbarBlock = Str::between($css, '.fi-topbar {', '.dark .fi-topbar {');
    $sidebarHeaderBlock = Str::between($css, '.fi-sidebar-header {', '.dark .fi-sidebar-header {');

    expect($topbarBlock)
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;")
        ->and($sidebarHeaderBlock)
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;");
});

test('open version footer matches topbar chrome height', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    $expectedHeight = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';
    $footerBlock = Str::between(
        $css,
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-version-footer {',
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-version-expanded {',
    );
    $expandedBlock = Str::between(
        $css,
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-version-expanded {',
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-version-footer {',
    );

    expect($footerBlock)
        ->toContain('width: 100%;')
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;")
        ->toContain("max-height: {$expectedHeight} !important;")
        ->and($expandedBlock)
        ->toContain('width: 100%;')
        ->toContain('min-width: 0;')
        ->and($provider)
        ->toContain('fi-sidebar-version-expanded')
        ->toContain('w-full min-w-0');
});

test('collapsed version footer is a square matching collapsed sidebar width', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    // Border-box height equals --collapsed-sidebar-width so 1px border-top
    // leaves a 71px content area (matches calc(4.5rem - 1px) target).
    $expectedHeight = 'var(--collapsed-sidebar-width, 4.5rem)';
    $footerBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-version-footer {',
        '.fi-sidebar-version-collapsed {',
    );

    expect($footerBlock)
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;")
        ->toContain("max-height: {$expectedHeight} !important;")
        ->toContain('padding-block: 0 !important;')
        ->and($provider)
        ->toContain('$store.sidebar.isOpen ? \\\'px-6 py-0\\\' : \\\'px-0 py-0\\\'');
});
