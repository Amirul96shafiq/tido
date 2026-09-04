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

test('select value marquee css hides the duplicate segment when not overflowing', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $segmentDisplayNeedle = <<<'CSS'
.tido-select-value-marquee
    .fi-select-input-ctn-option-labels-not-wrapped
    .fi-select-input-value-ctn
    .tido-text-marquee-segment {
    display: inline-block;
CSS;

    $duplicateHideNeedle = <<<'CSS'
.tido-select-value-marquee
    .tido-text-marquee-track:not(.is-overflowing)
    .tido-text-marquee-segment
    + .tido-text-marquee-segment {
    display: none;
CSS;

    $optionMarqueeNeedle = <<<'CSS'
.tido-select-value-marquee
    .fi-select-input-ctn-option-labels-not-wrapped
    .fi-select-input-option {
    min-width: 0;
    overflow: visible;
    padding-inline: 0.75rem;
CSS;

    $displayPos = strpos($css, $segmentDisplayNeedle);
    $hidePos = strpos($css, $duplicateHideNeedle);

    expect($displayPos)->not->toBeFalse()
        ->and($hidePos)->not->toBeFalse()
        ->and($hidePos)->toBeGreaterThan($displayPos)
        ->and($css)->toContain($optionMarqueeNeedle);
});
