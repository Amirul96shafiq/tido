<?php

use Illuminate\Support\Facades\Blade;

test('single-line text component renders the reusable hover-pan contract', function () {
    $html = Blade::render(
        '<x-tido.single-line-text class="flex-1" text-class="font-semibold">Long account name</x-tido.single-line-text>',
    );

    expect($html)
        ->toContain('tido-single-line-text-clip')
        ->toContain('x-ref="singleLineText"')
        ->toContain('tido-single-line-text')
        ->toContain('whitespace-nowrap')
        ->toContain('ResizeObserver')
        ->toContain('--tido-single-line-text-overflow')
        ->toContain('font-semibold')
        ->not->toContain('text-class=');
});
