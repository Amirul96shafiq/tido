<?php

declare(strict_types=1);

test('topbar and sidebar header height match collapsed sidebar width', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $expectedHeight = 'calc(var(--collapsed-sidebar-width, 4.5rem) - 1px)';

    $topbarBlock = Str::between($css, '.fi-topbar {', '.dark .fi-topbar {');
    $sidebarHeaderBlock = Str::between($css, '.fi-sidebar-header {', '.dark .fi-sidebar-header {');

    expect($topbarBlock)
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;")
        ->and($sidebarHeaderBlock)
        ->toContain("height: {$expectedHeight} !important;")
        ->toContain("min-height: {$expectedHeight} !important;");
});
