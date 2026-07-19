<?php

declare(strict_types=1);

test('go to bottom is fixed flush under topbar at collapsed chrome size', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $expectedSize = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';
    $block = Str::between($css, '.tido-go-to-bottom {', '.dark .tido-go-to-bottom {');

    expect($block)
        ->toContain('position: fixed;')
        ->toContain('right: 1px;')
        ->toContain("top: {$expectedSize};")
        ->toContain("width: {$expectedSize};")
        ->toContain("height: {$expectedSize};")
        ->toContain('border-bottom: 1px solid var(--color-gray-100);')
        ->toContain('border-left: 1px solid var(--color-gray-100);');
});

test('go to bottom is registered on panel body end', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)->toContain('<x-go-to-bottom />');
});

test('go to bottom uses arrow down icon without amber indicator', function () {
    $blade = (string) file_get_contents(resource_path('views/components/go-to-bottom.blade.php'));

    expect($blade)
        ->toContain('heroicon-o-arrow-down')
        ->toContain('Go to bottom')
        ->not->toContain('animate-ping')
        ->not->toContain('bg-amber-500')
        ->not->toContain('bg-amber-400');
});
