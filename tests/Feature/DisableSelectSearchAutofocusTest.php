<?php

declare(strict_types=1);

test('select search autofocus script blurs search on open and keeps type-ahead', function (): void {
    $js = (string) file_get_contents(resource_path('js/disable-select-search-autofocus.js'));

    expect($js)
        ->toContain('.fi-dropdown-panel input.fi-input[aria-label="Search"]')
        ->toContain('.fi-select-input-btn')
        ->toContain('allowNextSearchFocus')
        ->toContain('input.blur()')
        ->toContain('button.focus({ preventScroll: true })')
        ->toContain('event.key === \' \'');
});

test('select search autofocus script is registered on the admin panel', function (): void {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $vite = (string) file_get_contents(base_path('vite.config.js'));
    $docs = (string) file_get_contents(base_path('docs/vite-assets.md'));

    expect($provider)
        ->toContain('disable-select-search-autofocus')
        ->toContain('resources/js/disable-select-search-autofocus.js');

    expect($vite)->toContain('resources/js/disable-select-search-autofocus.js');

    expect($docs)->toContain('resources/js/disable-select-search-autofocus.js');
});
