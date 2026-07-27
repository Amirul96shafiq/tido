<?php

declare(strict_types=1);

test('go to top is fixed flush bottom-right at collapsed chrome size', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $expectedSize = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';
    $block = Str::between($css, '.tido-go-to-top {', '.dark .tido-go-to-top {');

    expect($block)
        ->toContain('position: fixed;')
        ->toContain('z-index: 15;')
        ->toContain('right: 1px;')
        ->toContain('bottom: 0;')
        ->toContain("width: {$expectedSize};")
        ->toContain("height: {$expectedSize};")
        ->toContain('border-top: 1px solid var(--color-gray-100);')
        ->toContain('border-left: 1px solid var(--color-gray-100);');
});

test('go to top is hidden on small viewports', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect(Str::between(
        $css,
        '/* Hide sticky scroll CTAs on small viewports',
        '/*',
    ))
        ->toContain('@media (max-width: 639px)')
        ->toContain('.tido-go-to-top,')
        ->toContain('.tido-go-to-bottom {')
        ->toContain('display: none !important;');

    expect($css)->not->toContain('tido-go-to-top--page-bottom');
});

test('go to top is registered on panel body end', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)->toContain('<x-go-to-top />');
});

test('go to top uses arrow up icon without amber indicator', function () {
    $blade = (string) file_get_contents(resource_path('views/components/go-to-top.blade.php'));

    expect($blade)
        ->toContain('heroicon-o-arrow-up')
        ->toContain('Go to top')
        ->not->toContain('atPageBottom')
        ->not->toContain('tido-go-to-top--page-bottom')
        ->not->toContain('animate-ping')
        ->not->toContain('bg-amber-500')
        ->not->toContain('bg-amber-400');
});
