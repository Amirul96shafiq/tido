<?php

declare(strict_types=1);

use Illuminate\Support\Str;

test('panel surfaces share the chrome border token', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--tido-border-color: var(--color-gray-100);')
        ->toContain('--tido-border-color: color-mix(')
        ->toContain('.fi-section:not(.fi-danger-zone-section > .fi-section),')
        ->toContain('.fi-ta-ctn,')
        ->toContain('.fi-tabs,')
        ->toContain('.fi-wi-stats-overview-stat,')
        ->toContain('.fi-input-wrp:not(.fi-invalid):not(:hover):not(:focus):not(:focus-within),')
        ->toContain('.fi-input-wrp:not(.fi-invalid):not(.fi-disabled):hover,')
        ->toContain('.fi-input-wrp:not(.fi-invalid):not(.fi-disabled):focus,')
        ->toContain('.fi-input-wrp:not(.fi-invalid):not(.fi-disabled):focus-within {')
        ->toContain('border-color: var(--primary-600) !important;')
        ->toContain('border-color: var(--primary-500) !important;')
        ->toContain('.fi-sidebar {')
        ->toContain('border-style: solid !important;')
        ->toContain('border-width: 1px !important;')
        ->toContain('border-color: var(--tido-border-color) !important;')
        ->toContain(
            '--tw-ring-shadow: 0 0 0 0 transparent !important;',
        );
});

test('neutral custom borders preserve interactive border states', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('[class~="border-gray-200"]')
        ->toContain('[class~="dark:border-slate-700"]')
        ->toContain('[class~="ring-gray-950/5"]')
        ->toContain('[class~="dark:ring-white/10"]')
        ->toContain('box-shadow: none !important;')
        ->toContain('box-shadow: none;')
        ->toContain('):not(:hover):not(:focus):not(:focus-within) {');
});

test('personalize previews keep neutral borders static on hover', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $previewBlock = Str::between(
        $css,
        '/* Personalize previews are illustrative surfaces, so their neutral borders and rings stay static on hover. */',
        '/* The dashboard stats schema uses a non-contained section as a layout wrapper. */',
    );

    expect($previewBlock)
        ->not->toBeEmpty()
        ->toContain('.tido-preview-static,')
        ->toContain('border-color: var(--tido-border-color) !important;')
        ->toContain('--tw-ring-shadow: 0 0 0 0 transparent !important;')
        ->toContain('box-shadow: none !important;')
        ->not->toContain(':hover');
});

test('dashboard stats wrapper does not add a duplicate outer border', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain(
            '.tido-dashboard-page .fi-section:has(.fi-wi-stats-overview-stat) {',
        )
        ->toContain('border-width: 0 !important;')
        ->toContain('--tw-ring-shadow: 0 0 0 0 transparent !important;');
});
