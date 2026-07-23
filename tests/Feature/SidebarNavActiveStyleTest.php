<?php

declare(strict_types=1);

test('sidebar nav active and hover styles use theme switcher tokens', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $sidebarNavBlock = Str::between(
        $css,
        '/* Sidebar navigation — match user-menu profile active / theme-switcher tokens */',
        '.fi-sidebar-item-btn {',
    );

    expect($sidebarNavBlock)
        ->toContain('background-color: var(--gray-50);')
        ->toContain('color: var(--primary-500);')
        ->toContain('color: var(--primary-400);')
        ->toContain('color-mix(in oklab, var(--color-white) 5%, transparent)')
        ->toContain('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon')
        ->toContain('color: inherit;')
        ->not->toContain('text-amber-500')
        ->not->toContain('bg-gray-100')
        ->not->toContain('slate-700/60');
});
