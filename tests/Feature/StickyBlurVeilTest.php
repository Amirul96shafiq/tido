<?php

declare(strict_types=1);

use Illuminate\Support\Str;

test('sticky blur veil script caches pin offsets and marks native scrolling', function () {
    $js = (string) file_get_contents(resource_path('js/sticky-blur-veil.js'));

    expect($js)
        ->toContain('const STUCK_CLASS = "tido-sticky-stuck";')
        ->toContain('const SCROLLING_CLASS = "tido-is-scrolling";')
        ->toContain('metricsByPin')
        ->toContain('previous === stuck && hasClass === stuck')
        ->toContain('function bottomReferenceEdge()')
        ->toContain('function isScrolledToEnd(')
        ->toContain('function pageScrollRoot()')
        ->toContain('overflowY === "auto" || overflowY === "scroll"')
        ->toContain('insetDelta > -2 && insetDelta < 16')
        ->toContain('return !isScrolledToEnd(pageScrollRoot())')
        ->toContain('Math.abs(rect.top - metrics.expectedOffset) < 2')
        ->toContain('document.documentElement.classList.add(SCROLLING_CLASS)')
        ->toContain('document.documentElement.classList.remove(SCROLLING_CLASS)')
        ->toContain('onScrollCapture')
        ->toContain('isPageScrollTarget')
        ->toContain('capture: true')
        ->toContain('livewire:navigated')
        ->not->toContain('ScrollSmoother')
        ->not->toContain('gsap')
        ->not->toContain('127.0.0.1:7630')
        ->not->toContain('agent log');
});

test('sticky blur veil applies backdrop filter only while stuck', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $unstuckBefore = Str::between(
        $css,
        '/* Long soft tint. Blur is applied only while .tido-sticky-stuck so Chromium',
        '/* Stronger tint nearer the edge. Blur is applied only while stuck.',
    );

    expect($unstuckBefore)
        ->not->toContain('backdrop-filter:')
        ->and($css)
        ->toContain('.tido-sticky-stuck::before {')
        ->toContain('backdrop-filter: blur(3px);')
        ->toContain('.tido-sticky-stuck::after {')
        ->toContain('backdrop-filter: blur(8px);')
        ->toContain('html.tido-is-scrolling')
        ->toContain('.tido-sticky-stuck::before,')
        ->toContain('backdrop-filter: none;')
        ->toContain('inset-inline-start: var(--sidebar-width, 18rem);')
        ->toContain('inset-inline-start: var(--collapsed-sidebar-width, 4.5rem);');
});

test('panel does not install a transform-based smooth scroll library', function () {
    $package = (string) file_get_contents(base_path('package.json'));
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $appJs = (string) file_get_contents(resource_path('js/app.js'));

    expect($package)
        ->not->toContain('"gsap"')
        ->not->toContain('"lenis"')
        ->and($appJs)
        ->not->toContain('gsap')
        ->not->toContain('ScrollSmoother')
        ->and($css)
        ->not->toContain('scroll-behavior: smooth');
});
