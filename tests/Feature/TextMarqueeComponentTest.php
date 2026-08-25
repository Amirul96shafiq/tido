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
        ->toContain('prefersReducedMotion()')
        ->toContain('tidoPrefersReducedMotion')
        ->toContain('--tido-marquee-distance')
        ->toContain('--tido-marquee-duration')
        ->toContain('font-semibold')
        ->toContain('aria-hidden="true"')
        ->not->toContain('x-ref="marqueeText"')
        ->not->toContain('tido-single-line-text')
        ->not->toContain('text-class=')
        ->not->toContain('requestAnimationFrame(tick')
        ->not->toContain('track.style.transform');
});

test('text marquee css drives overflow motion on the compositor', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('@keyframes tido-text-marquee-scroll')
        ->toContain('.tido-text-marquee-track.is-overflowing')
        ->toContain('animation: tido-text-marquee-scroll')
        ->toContain('--tido-marquee-distance')
        ->toContain('--tido-marquee-duration')
        ->toContain('html.tido-reduce-motion .tido-text-marquee-track')
        ->toContain('html.tido-reduce-motion .tido-text-marquee-clip')
        ->toContain('html.tido-reduce-motion .tido-text-marquee-segment')
        ->toContain('white-space: normal !important');
});
