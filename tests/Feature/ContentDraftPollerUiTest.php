<?php

declare(strict_types=1);

test('draft saved poller is fixed at top-start clear of sidebar and topbar', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $blade = (string) file_get_contents(resource_path('views/filament/hooks/content-draft-poller.blade.php'));

    $block = Str::between($css, '.fi-content-draft-poller {', '.fi-sidebar-item-btn > .fi-icon,');

    expect($block)
        ->toContain('position: fixed;')
        ->toContain('z-index: 40;')
        ->toContain('pointer-events: none;')
        ->toContain('top: calc(var(--collapsed-sidebar-width, 4.5rem) - 1px + 1rem);')
        ->toContain('inset-inline-start: 1rem;')
        ->toContain('inset-inline-start: calc(var(--collapsed-sidebar-width, 4.5rem) + 1rem);')
        ->toContain('inset-inline-start: calc(var(--sidebar-width, 18rem) + 1rem);')
        ->toContain(':has(.fi-main-ctn-sidebar-open)')
        ->not->toContain('inset-inline-end')
        ->not->toContain('right: 1px');

    expect($blade)
        ->toContain('class="fi-content-draft-poller"')
        ->not->toContain('inset-e-')
        ->not->toContain('fixed inset');
});
