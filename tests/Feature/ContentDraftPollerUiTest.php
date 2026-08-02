<?php

declare(strict_types=1);

test('draft saved poller is rendered inside the sticky bottom action row', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $blade = (string) file_get_contents(resource_path('views/filament/hooks/content-draft-poller.blade.php'));

    $block = Str::between($css, '.fi-content-draft-poller {', '.fi-sidebar-item-btn > .fi-icon,');

    expect($block)
        ->toContain('display: flex;')
        ->toContain('width: max-content;')
        ->toContain('max-width: 100%;')
        ->toContain('margin-inline-start: auto;')
        ->toContain('justify-content: flex-end;')
        ->toContain('pointer-events: none;')
        ->toContain('flex-shrink: 0;')
        ->not->toContain('position: fixed;')
        ->not->toContain('top: calc(')
        ->not->toContain('inset-inline-start');

    expect($blade)
        ->toContain('class="fi-content-draft-poller"')
        ->not->toContain('inset-e-')
        ->not->toContain('fixed inset')
        ->toContain('x-transition:enter-start="opacity-0 translate-y-4"');
});
