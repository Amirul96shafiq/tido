<?php

declare(strict_types=1);

test('admin panel light and dark background images exist', function () {
    expect(public_path('images/bg-l.png'))->toBeFile()
        ->and(public_path('images/bg-d.png'))->toBeFile();
});

test('admin panel css applies theme-aware background images', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $bodyBlock = Str::between($css, '/* Theme-aware panel background', '/* Auth simple pages');

    expect($bodyBlock)
        ->toContain('background-image: var(--tido-bg-light);')
        ->toContain('background-size: cover;')
        ->toContain('background-attachment: fixed;')
        ->toContain('background-position: bottom center;')
        ->toContain('.dark .fi-body {')
        ->toContain('background-image: var(--tido-bg-dark);')
        ->not->toContain('background-size: contain')
        ->not->toContain('.fi-body::before')
        ->not->toContain('linear-gradient');
});

test('admin panel provider injects theme background asset css variables', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)
        ->toContain("asset('images/bg-l.png')")
        ->toContain("asset('images/bg-d.png')")
        ->toContain('--tido-bg-light:')
        ->toContain('--tido-bg-dark:');
});

test('dark mode form fields and repeater items match section surface and border', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $fieldsBlock = Str::between(
        $css,
        '/* Form fields + nested repeater/builder items — match section / widget',
        '/* Auth simple pages',
    );

    expect($fieldsBlock)
        ->toContain('.dark .fi-input-wrp:not(.fi-disabled),')
        ->toContain('.dark .fi-fo-file-upload .filepond--root:not([data-disabled=\'disabled\']),')
        ->toContain('.dark .fi-fo-repeater-item,')
        ->toContain('.dark .fi-fo-builder-item {')
        ->toContain('@apply bg-gray-900 ring-white/10;')
        ->and($css)
        ->toContain('dark:bg-gray-900 dark:ring-white/10');
});

test('auth light and dark background images exist', function () {
    expect(public_path('images/auth-bg-l.png'))->toBeFile()
        ->and(public_path('images/auth-bg-d.png'))->toBeFile();
});

test('admin panel provider injects auth background asset css variables', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)
        ->toContain("asset('images/auth-bg-l.png')")
        ->toContain("asset('images/auth-bg-d.png')")
        ->toContain('--tido-auth-bg-light:')
        ->toContain('--tido-auth-bg-dark:');
});

test('auth simple pages use desktop 30/70 split background layout', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $authSplitBlock = Str::between(
        $css,
        '/* Auth simple pages — 30/70 split',
        '/* Auth simple pages — Catalyst-style',
    );

    expect($authSplitBlock)
        ->toContain('.fi-body:has(.fi-simple-layout) {')
        ->toContain('background-image: none !important;')
        ->toContain('@media (min-width: 1024px)')
        ->toContain('.fi-simple-layout::before {')
        ->toContain('inset-inline-start: 0;')
        ->toContain('width: 30%;')
        ->toContain('background-image: var(--tido-auth-bg-light);')
        ->toContain('background-position: center;')
        ->toContain('.dark .fi-simple-layout::before {')
        ->toContain('background-image: var(--tido-auth-bg-dark);')
        ->toContain('.fi-simple-main-ctn {')
        ->toContain('width: 70%;')
        ->toContain('margin-inline-start: auto;');
});
