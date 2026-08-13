<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('text marquee component renders the looping track contract', function () {
    $html = Blade::render(
        '<x-tido.text-marquee class="flex-1" text-class="font-semibold whitespace-nowrap">Long account name</x-tido.text-marquee>',
    );

    expect($html)
        ->toContain('tido-text-marquee-clip')
        ->toContain('tido-text-marquee-track')
        ->toContain('tido-text-marquee-segment')
        ->toContain('x-ref="marqueeTrack"')
        ->toContain('x-ref="marqueeSegment"')
        ->toContain('wire:ignore')
        ->toContain('whitespace-nowrap')
        ->toContain('ResizeObserver')
        ->toContain('font-semibold')
        ->toContain('aria-hidden="true"')
        ->not->toContain('x-ref="marqueeText"')
        ->not->toContain('tido-single-line-text')
        ->not->toContain('text-class=');
});
