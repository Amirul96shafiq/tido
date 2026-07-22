<?php

declare(strict_types=1);

test('admin panel light and dark background images exist', function () {
    expect(public_path('images/bg-l.png'))->toBeFile()
        ->and(public_path('images/bg-d.png'))->toBeFile()
        ->and(public_path('images/bg-enabled-l-v2.png'))->toBeFile()
        ->and(public_path('images/bg-enabled-d-v2.png'))->toBeFile()
        ->and(public_path('images/bg-disabled-l-v2.png'))->toBeFile()
        ->and(public_path('images/bg-disabled-d-v2.png'))->toBeFile();
});

test('admin panel css applies theme-aware background images', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $bodyBlock = Str::between($css, '/* Theme-aware panel background', '/* Auth simple pages');

    expect($bodyBlock)
        ->toContain('background-color: var(--tido-bg-color-light, var(--color-white)) !important;')
        ->toContain('background-image: var(--tido-bg-light);')
        ->toContain('background-size: cover;')
        ->toContain('background-attachment: fixed;')
        ->toContain('background-position: bottom center;')
        ->toContain('.dark .fi-body {')
        ->toContain('background-color: var(--tido-bg-color-dark, var(--color-slate-800)) !important;')
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
        ->toContain("getAttribute('stylized_background_enabled')")
        ->toContain(": 'none';")
        ->toContain('--tido-bg-light:')
        ->toContain('--tido-bg-dark:')
        ->toContain('--tido-bg-color-light: #FFFFFF;')
        ->toContain('--tido-bg-color-dark: #1D293D;');
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
        ->and(public_path('images/auth-bg-d.png'))->toBeFile()
        ->and(public_path('images/auth-bg-l-v2.png'))->toBeFile()
        ->and(public_path('images/auth-bg-d-v2.png'))->toBeFile();
});

test('admin panel provider injects auth background asset css variables', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)
        ->toContain("asset('images/auth-bg-l.png')")
        ->toContain("asset('images/auth-bg-d.png')")
        ->toContain("asset('images/auth-bg-l-v2.png')")
        ->toContain("asset('images/auth-bg-d-v2.png')")
        ->toContain('--tido-auth-bg-light-mobile:')
        ->toContain('--tido-auth-bg-dark-mobile:')
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
        ->toContain('inset-inline: 0;')
        ->toContain('inset-block-end: 0;')
        ->toContain('height: 20%;')
        ->not->toContain('border-radius: 0.75rem 0.75rem 0 0;')
        ->toContain('background-image: var(--tido-auth-bg-light-mobile);')
        ->toContain('--tido-auth-bg-pos-x: 50%;')
        ->toContain('--tido-auth-bg-pos-y: 78%;')
        ->toContain('background-position: var(--tido-auth-bg-pos-x) var(--tido-auth-bg-pos-y);')
        ->toContain('.dark .fi-simple-layout::before {')
        ->toContain('background-image: var(--tido-auth-bg-dark-mobile);')
        ->toContain('--tido-auth-bg-pos-x: 66%;')
        ->toContain('--tido-auth-bg-pos-y: 72%;')
        ->toContain('@media (max-width: 1023px)')
        ->toContain('--tido-auth-bg-pos-y: calc(78% - 50px);')
        ->toContain('--tido-auth-bg-pos-y: calc(72% - 30px);')
        ->toContain('overflow: hidden !important;')
        ->toContain('height: 100dvh;')
        ->toContain('max-height: 80%;')
        ->toContain('@media (min-width: 1024px)')
        ->toContain('inset-block: 0;')
        ->toContain('inset-inline-start: 0;')
        ->toContain('inset-inline-end: auto;')
        ->toContain('width: calc(100dvh * 1000 / 1536);')
        ->toContain('height: auto;')
        ->toContain('background-image: var(--tido-auth-bg-light);')
        ->toContain('background-image: var(--tido-auth-bg-dark);')
        ->toContain('background-size: cover;')
        ->not->toContain('border-radius: 0.75rem;')
        ->toContain('--tido-auth-bg-pos-y: 62%;')
        ->toContain('--tido-auth-bg-pos-y: 58%;')
        ->toContain('.fi-simple-main-ctn {')
        ->toContain('width: calc(100% - (100dvh * 1000 / 1536));')
        ->toContain('margin-inline-start: auto;')
        ->toContain('padding-block-end: 0;');
});
