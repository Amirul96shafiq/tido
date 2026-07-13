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
