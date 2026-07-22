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

test('auth simple pages use mobile bottom and desktop left split layout', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $authSplitBlock = Str::between(
        $css,
        '/* Auth simple pages — split',
        '/* Auth simple pages — Catalyst-style',
    );

    expect($authSplitBlock)
        ->toContain('.fi-body:has(.fi-simple-layout) {')
        ->toContain('background-image: none !important;')
        ->toContain('.tido-go-to-top,')
        ->toContain('.tido-go-to-bottom {')
        ->toContain('display: none !important;')
        ->toContain('.fi-simple-layout::before {')
        ->toContain('inset-inline: 1.5rem;')
        ->toContain('inset-block-end: 0;')
        ->toContain('height: calc(20% - 1.5rem);')
        ->toContain('border-radius: 0.75rem 0.75rem 0 0;')
        ->toContain('background-image: var(--tido-auth-bg-light);')
        ->toContain('--tido-auth-bg-pos-x: 50%;')
        ->toContain('--tido-auth-bg-pos-y: 78%;')
        ->toContain('background-position: var(--tido-auth-bg-pos-x) var(--tido-auth-bg-pos-y);')
        ->toContain('.dark .fi-simple-layout::before {')
        ->toContain('background-image: var(--tido-auth-bg-dark);')
        ->toContain('--tido-auth-bg-pos-x: 66%;')
        ->toContain('--tido-auth-bg-pos-y: 72%;')
        ->toContain('@media (max-width: 1023px)')
        ->toContain('--tido-auth-bg-pos-y: calc(78% - 50px);')
        ->toContain('--tido-auth-bg-pos-y: calc(72% - 30px);')
        ->toContain('overflow: hidden !important;')
        ->toContain('height: 100dvh;')
        ->toContain('max-height: 80%;')
        ->toContain('@media (min-width: 1024px)')
        ->toContain('inset-block: 1.5rem;')
        ->toContain('inset-inline-start: 1.5rem;')
        ->toContain('inset-inline-end: auto;')
        ->toContain('width: calc(30% - 2.25rem);')
        ->toContain('height: auto;')
        ->toContain('border-radius: 0.75rem;')
        ->toContain('--tido-auth-bg-pos-y: 62%;')
        ->toContain('--tido-auth-bg-pos-y: 58%;')
        ->toContain('.fi-simple-main-ctn {')
        ->toContain('width: 70%;')
        ->toContain('margin-inline-start: auto;')
        ->toContain('padding-block-end: 0;');
});
