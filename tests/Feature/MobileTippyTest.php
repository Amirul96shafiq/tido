<?php

declare(strict_types=1);

test('app css hides tippy roots below the sm breakpoint', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('@media (max-width: 639px)')
        ->toContain('[data-tippy-root]')
        ->toContain('display: none !important;');
});

test('disable mobile tippy script cancels tippy show below sm', function () {
    $js = (string) file_get_contents(resource_path('js/disable-mobile-tippy.js'));

    expect($js)
        ->toContain("matchMedia('(max-width: 639px)')")
        ->toContain('onShow')
        ->toContain('touch:')
        ->toContain('Chart.js widget tooltips are unaffected');
});

test('disable mobile tippy is registered on the admin panel', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $vite = (string) file_get_contents(base_path('vite.config.js'));

    expect($provider)
        ->toContain('disable-mobile-tippy')
        ->toContain('resources/js/disable-mobile-tippy.js');

    expect($vite)->toContain('resources/js/disable-mobile-tippy.js');
});
