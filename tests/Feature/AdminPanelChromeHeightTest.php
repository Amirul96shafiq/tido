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

test('main content min-height matches tido topbar not Filament 4rem', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $mainCtnBlock = Str::between(
        $css,
        '.fi-body-has-sidebar-collapsible-on-desktop .fi-main-ctn {',
        '.fi-body-has-sidebar-collapsible-on-desktop .fi-main-ctn-sidebar-open {',
    );

    expect($mainCtnBlock)
        ->toContain('min-height: calc(')
        ->toContain('100dvh - (var(--collapsed-sidebar-width, 4.5rem) - 1px)')
        ->not->toContain('100dvh - 4rem')
        ->not->toContain('100dvh-4rem');
});

test('page header main container shaves 1px bottom padding for fold-packed lists', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $block = Str::between(
        $css,
        '.fi-page-header-main-ctn {',
        '/*\n * Filament header.css hides breadcrumbs below sm',
    );

    expect($block)
        ->toContain('padding-bottom: calc(2rem - 1px);');
});

test('open sidebar collapse footer matches topbar chrome height', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    $expectedHeight = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';
    $footerBlock = Str::between(
        $css,
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-collapse-footer {',
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-collapse-buttons {',
    );
    $buttonsBlock = Str::between(
        $css,
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-collapse-buttons {',
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-collapse-footer {',
    );

    expect($footerBlock)
        ->toContain('width: 100%;')
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;")
        ->toContain("max-height: {$expectedHeight} !important;")
        ->and($buttonsBlock)
        ->toContain('width: 100%;')
        ->toContain('min-width: 0;')
        ->and($provider)
        ->toContain('fi-sidebar-collapse-footer')
        ->toContain('fi-sidebar-close-collapse-sidebar-btn')
        ->toContain('fi-sidebar-open-collapse-sidebar-btn');
});

test('collapsed sidebar collapse footer is a square matching collapsed sidebar width', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    // Border-box height equals --collapsed-sidebar-width so 1px border-top
    // leaves a 71px content area (matches calc(4.5rem - 1px) target).
    $expectedHeight = 'var(--collapsed-sidebar-width, 4.5rem)';
    $footerBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-collapse-footer {',
        '.fi-sidebar-collapse-buttons {',
    );

    expect($footerBlock)
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;")
        ->toContain("max-height: {$expectedHeight} !important;")
        ->toContain('padding-block: 0 !important;')
        ->and($provider)
        ->toContain('class="fi-sidebar-collapse-footer"')
        ->toContain('class="fi-sidebar-collapse-buttons');
});

test('sidebar footer owns collapse buttons and header only owns logo', function () {
    $sidebar = (string) file_get_contents(
        resource_path('views/vendor/filament-panels/livewire/sidebar.blade.php'),
    );
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $footerHookPos = strpos($provider, 'PanelsRenderHook::SIDEBAR_FOOTER');
    $collapsePos = strpos($provider, 'fi-sidebar-collapse-footer');
    $logoPos = strpos($sidebar, 'fi-sidebar-header-logo-ctn');
    $openLogoBlock = Str::between(
        $css,
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-header-logo-ctn {',
        "/*\n * Header chrome",
    );
    $openHeaderBlock = Str::between(
        $css,
        '.fi-sidebar.fi-sidebar-open .fi-sidebar-header {',
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-header {',
    );

    expect($footerHookPos)->not->toBeFalse()
        ->and($collapsePos)->not->toBeFalse()
        ->and($collapsePos)->toBeGreaterThan($footerHookPos)
        ->and($logoPos)->not->toBeFalse()
        ->and($sidebar)
        ->not->toContain('fi-sidebar-collapse-btns')
        ->and($css)
        ->toContain('.fi-sidebar.fi-sidebar-open .fi-sidebar-header-logo-ctn')
        ->toContain('justify-content: flex-start;')
        ->and($openLogoBlock)
        ->toContain('justify-content: flex-start;')
        ->not->toContain('justify-content: flex-end;')
        ->and($openHeaderBlock)
        ->toContain('padding-inline: 1rem !important;');
});

test('sidebar header swaps full and compact logos by state', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $sidebar = (string) file_get_contents(
        resource_path('views/vendor/filament-panels/livewire/sidebar.blade.php'),
    );

    $collapsedFullBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-logo-full {',
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-logo-compact {',
    );
    $collapsedCompactBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-logo-compact {',
        '.fi-sidebar-logo-compact .fi-logo {',
    );

    expect($sidebar)
        ->toContain('fi-sidebar-logo-full')
        ->toContain('fi-sidebar-logo-compact')
        ->toContain('tido_dark_logo_c.png')
        ->toContain('tido_light_logo_c.png')
        ->and($collapsedFullBlock)
        ->toContain('display: none;')
        ->and($collapsedCompactBlock)
        ->toContain('display: flex;')
        ->toContain('justify-content: center;')
        ->and($css)
        ->toContain('.fi-topbar-start {')
        ->toContain('.fi-topbar-start .fi-logo')
        ->toContain('.fi-topbar .fi-logo')
        ->toContain('display: flex !important;')
        ->toContain('@media (min-width: 1024px)')
        ->not->toContain('.fi-topbar-ctn-collapsed .fi-topbar-start')
        ->not->toContain('html.fi-sidebar-is-collapsed .fi-topbar-start');

    $mobileTopbarLogoSection = Str::between(
        $css,
        '/*\n * Mobile topbar: brand logo beside the sidebar open/close buttons.',
        '/* Skip layout/chrome motion on the first paint after a hard refresh */',
    );
    $mobileTopbarStartBlock = Str::between(
        $mobileTopbarLogoSection,
        '.fi-topbar-start {',
        '.fi-topbar-start .fi-logo {',
    );

    expect($mobileTopbarStartBlock)
        ->toContain('display: flex !important;')
        ->and($mobileTopbarLogoSection)
        ->toContain('@media (min-width: 1024px)')
        ->toContain('.fi-topbar .fi-logo')
        ->toContain('display: none !important;')
        ->toContain('height: 2.5rem !important;');
});

test('sidebar collapse expand transition uses shared motion tokens and logo mask', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $collapsedFullBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-logo-full {',
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-logo-compact {',
    );
    $collapsedCompactBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-logo-compact {',
        '.fi-sidebar-logo-compact .fi-logo {',
    );
    $sidebarHeaderBlock = Str::between(
        $css,
        '.fi-sidebar-header {',
        '.dark .fi-sidebar-header {',
    );
    $reducedMotionBlock = Str::between(
        $css,
        '/* Honor prefers-reduced-motion: skip sidebar collapse/expand chrome motion */',
        '/*\n * Filament .fi-page-header-main-ctn uses py-8.',
    );

    expect($css)
        ->toContain('--tido-sidebar-duration: 300ms;')
        ->toContain('--tido-sidebar-ease: cubic-bezier(0.4, 0, 0.2, 1);')
        ->toContain('--tido-sidebar-content-delay: 120ms;')
        ->toContain('html.fi-sidebar-preload .fi-sidebar-logo-full')
        ->toContain('html.fi-sidebar-preload .fi-sidebar-logo-compact')
        ->toContain(
            'animation: fi-collapsed-chrome-enter var(--tido-sidebar-duration)',
        )
        ->toContain(
            'transition: width var(--tido-sidebar-duration) var(--tido-sidebar-ease) !important;',
        )
        ->and($collapsedFullBlock)
        ->toContain('display: none;')
        ->and($collapsedCompactBlock)
        ->toContain('display: flex;')
        ->and($sidebarHeaderBlock)
        ->toContain('overflow: hidden;')
        ->and($reducedMotionBlock)
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('.fi-sidebar-logo-full')
        ->toContain('.fi-sidebar-logo-compact')
        ->toContain('.fi-sidebar-item-btn')
        ->toContain('transition: none !important;')
        ->toContain('animation: none !important;');
});
