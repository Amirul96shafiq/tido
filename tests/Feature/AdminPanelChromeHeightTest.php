<?php

declare(strict_types=1);

test('desktop sidebar stacks above topbar without trapping in layout z-index', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $stackingComment = 'Do not put z-index on .fi-layout:';
    $stackingSection = Str::between(
        $css,
        $stackingComment,
        '.fi-body:has(.fi-simple-layout) .tido-stylized-bg {',
    );
    $layoutBlock = Str::between($stackingSection, '.fi-layout {', '.fi-main-ctn {');
    $mainCtnLiftBlock = Str::after($stackingSection, '.fi-main-ctn {');
    $desktopChromeBlock = Str::between(
        $css,
        '/* Desktop layout overrides for full-height sidebar and static topbar */',
        '/* Honor prefers-reduced-motion: skip sidebar collapse/expand chrome motion */',
    );

    expect($css)
        ->toContain($stackingComment)
        ->and($layoutBlock)
        ->toContain('position: relative;')
        ->not->toContain('z-index:')
        ->and($mainCtnLiftBlock)
        ->toContain('position: relative;')
        ->toContain('z-index: 1;')
        ->and($desktopChromeBlock)
        ->toContain('z-index: 30 !important;')
        ->toContain('z-index: 20 !important;');
});

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
        ->toContain('transition: margin-inline-start var(--tido-sidebar-duration)')
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

test('open sidebar collapse control is labeled button without tooltip', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $buttonsMarkup = Str::between(
        $provider,
        'class="fi-sidebar-collapse-buttons flex h-full w-full items-center px-4">',
        '@endif',
    );

    $morphCss = Str::between(
        $css,
        '/* Square → rectangle morph shell',
        '/* Go to top — flush bottom-right square matching collapsed collapse footer (71×71)',
    );

    expect($buttonsMarkup)
        ->toContain('fi-sidebar-collapse-morph')
        ->toContain('fi-sidebar-close-collapse-sidebar-btn')
        ->toContain('fi-sidebar-open-collapse-sidebar-btn')
        ->toContain('fi-sidebar-collapse-toggle-label')
        ->toContain("{{ __('filament-panels::layout.actions.sidebar.collapse.label') }}")
        ->toContain('label-sr-only')
        ->toContain(":tooltip=\"__('filament-panels::layout.actions.sidebar.expand.label')\"")
        ->not->toContain('x-show="$store.sidebar.isOpen"')
        ->not->toContain('<x-filament::icon-button')
        ->and($morphCss)
        ->toContain('.fi-sidebar-collapse-morph')
        ->toContain('width: 2.5rem;')
        ->toContain('.fi-sidebar.fi-sidebar-open .fi-sidebar-collapse-morph')
        ->toContain('width: 100%;')
        ->toContain('padding-inline-start: 0.625rem !important;')
        ->toContain('.fi-sidebar-collapse-morph .fi-sidebar-close-collapse-sidebar-btn')
        ->toContain('z-index: 1;')
        ->toContain('.fi-sidebar-collapse-morph .fi-sidebar-open-collapse-sidebar-btn')
        ->toContain('z-index: 2;')
        ->toContain('.fi-sidebar.fi-sidebar-open .fi-sidebar-close-collapse-sidebar-btn')
        ->toContain('opacity: 1;')
        ->toContain('opacity 0s')
        ->toContain('.fi-sidebar.fi-sidebar-open .fi-sidebar-open-collapse-sidebar-btn')
        ->toContain(
            'visibility 0s linear',
        );
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
        ->toContain('class="fi-sidebar-collapse-buttons')
        ->toContain('stabilizeSidebarChrome')
        ->toContain("classList.contains('fi-sidebar-preload')")
        ->toContain('spaNavigating')
        ->toContain("sidebar.style.setProperty('transition', 'none', 'important')")
        ->toContain('requestAnimationFrame(() => $store.sidebar.open())')
        ->not->toContain('127.0.0.1:7630')
        ->not->toContain('agent log')
        ->not->toContain('__tidoPrepareSidebarClip');
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
        ->not->toContain('x-persist="sidebar.panel-')
        ->toContain('data-sidebar-home="')
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
        ->toContain('--tido-sidebar-duration: 520ms;')
        ->toContain('--tido-sidebar-ease: cubic-bezier(0.45, 0.05, 0.15, 1);')
        ->toContain('--tido-sidebar-content-delay: 340ms;')
        ->toContain('html.fi-sidebar-preload .fi-sidebar-logo-full')
        ->toContain('html.fi-sidebar-preload .fi-sidebar-logo-compact')
        ->toContain('html:not(.fi-sidebar-preload) .fi-sidebar[x-cloak]')
        ->toContain(
            '.fi-sidebar.fi-sidebar-animating:not(.fi-sidebar-open) .fi-sidebar-item-btn',
        )
        ->toContain(
            '.fi-sidebar.fi-sidebar-animating.fi-sidebar-open .fi-sidebar-logo-full',
        )
        ->toContain(
            'animation: fi-collapsed-chrome-enter var(--tido-sidebar-duration)',
        )
        ->toContain(
            'animation: fi-sidebar-expand-chrome-enter var(--tido-sidebar-duration)',
        )
        ->toContain('@keyframes fi-sidebar-expand-chrome-enter')
        ->toContain(
            'transition: clip-path var(--tido-sidebar-duration)',
        )
        ->toContain(
            'transition: margin-inline-start var(--tido-sidebar-duration)',
        )
        ->toContain(
            'transition: padding-inline-start var(--tido-sidebar-duration)',
        )
        ->not->toContain(
            'transition: width var(--tido-sidebar-duration) var(--tido-sidebar-ease) !important;',
        )
        ->not->toContain('tido-sidebar-clip-collapsed')
        ->not->toContain('html.tido-sidebar-hold-collapsed-inset')
        ->not->toContain(
            '.fi-body-has-sidebar-collapsible-on-desktop:has(',
        )
        ->toContain('fi-sidebar-animating')
        ->toContain('fi-sidebar-group-heading')
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

test('collapsed sidebar nav icons share expanded icon inset via flex-start rail', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $collapsedItemBtnBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn {',
        '.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-dropdown-trigger-btn {',
    );
    $collapsedGroupTriggerBlock = Str::between(
        $css,
        '.fi-sidebar:not(.fi-sidebar-open)
    .fi-sidebar-group-dropdown-trigger-btn.fi-version-icon-btn {',
        '/*
 * Collapsed: left-aligned size-10 box',
    );

    expect($css)
        ->toContain('--tido-sidebar-nav-pad: 1.5rem;')
        ->toContain('--tido-sidebar-icon-pad: 0.5rem;')
        ->and($collapsedItemBtnBlock)
        ->toContain('justify-content: flex-start;')
        ->toContain('padding-inline-start: var(--tido-sidebar-icon-pad);')
        ->toContain('opacity: 1;')
        ->not->toContain('opacity: 0;')
        ->not->toContain('justify-content: center;')
        ->and($collapsedGroupTriggerBlock)
        ->toContain('justify-content: flex-start;')
        ->toContain('padding-inline-start: var(--tido-sidebar-icon-pad);')
        ->and($css)
        ->toContain('.fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups')
        ->toContain('margin-inline: -0.5rem;')
        ->toContain('padding-inline: var(--tido-sidebar-nav-pad);');
});

test('nested dropdown panels are not clipped by the parent dropdown overflow rule', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-dropdown > .fi-dropdown-panel.fi-scrollable {')
        ->toContain('max-height: min(40vh, 20rem) !important;')
        ->toContain('overflow-y: auto !important;')
        ->toContain('.fi-select-input-ctn > .fi-dropdown-panel.fi-scrollable {')
        ->toContain('max-height: min(60vh, 15rem) !important;')
        ->toContain('.fi-dropdown.fi-ta-filters-dropdown')
        ->toContain('.fi-dropdown.fi-ta-col-manager-dropdown')
        ->toContain('> .fi-dropdown-panel.fi-scrollable[style*="display: block"]')
        ->toContain('display: flex !important;')
        ->toContain('overflow-y: hidden !important;')
        ->toContain('.fi-ta-filters-dropdown .fi-ta-filters-body,')
        ->toContain('.fi-ta-col-manager-dropdown .fi-ta-col-manager-body {')
        ->toContain('.fi-fixed-positioning-context .fi-fo-date-time-picker-panel')
        ->toContain('z-index: 40;')
        ->toContain('.tido-date-picker-month-panel')
        ->not->toContain('.fi-ta-filters-body:has(')
        ->not->toContain(".fi-dropdown-panel {\n    max-height: 60vh !important;")
        ->not->toContain(".fi-dropdown-panel {\n    max-height: min(40vh, 20rem) !important;")
        ->not->toContain(".fi-dropdown-panel {\n    overflow-y: auto !important;");
});

test('open form dropdowns stay below sticky controls while modals stay above them', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $dropdownBlock = Str::betweenFirst(
        $css,
        'Sticky markers must paint above a form column',
        'Filament action modals',
    );
    $modalBlock = Str::betweenFirst(
        $css,
        'Filament action modals',
        '.tido-sticky-scope > .fi-sc > .fi-grid-col:has(.tido-sticky-marker--top)',
    );

    expect($dropdownBlock)
        ->toContain('z-index: 5;')
        ->not->toContain('z-index: 15;')
        ->and($modalBlock)
        ->toContain('z-index: 15;');
});
