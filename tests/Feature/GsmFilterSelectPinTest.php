<?php

declare(strict_types=1);

test('gsm filter select pin script pins option panels inside global search filters', function (): void {
    $js = (string) file_get_contents(resource_path('js/gsm-filter-select-pin.js'));

    expect($js)
        ->toContain('[id="global-search-modal::plugin"]')
        ->toContain('.fi-gsm-filters-dropdown-panel')
        ->toContain('.fi-select-input-ctn > .fi-dropdown-panel.fi-scrollable')
        ->toContain('data-tido-gsm-select-fixed-pinned')
        ->toContain('setProperty("position", "fixed", "important")')
        ->toContain('.fi-gsm-toolbar')
        ->toContain('getFixedCoordsForTrigger')
        ->toContain('.fi-gsm-toolbar');
});

test('gsm filter select pin script is registered on the admin panel', function (): void {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $vite = (string) file_get_contents(base_path('vite.config.js'));
    $docs = (string) file_get_contents(base_path('docs/vite-assets.md'));

    expect($provider)
        ->toContain('gsm-filter-select-pin')
        ->toContain('resources/js/gsm-filter-select-pin.js');

    expect($vite)->toContain('resources/js/gsm-filter-select-pin.js');

    expect($docs)->toContain('resources/js/gsm-filter-select-pin.js');
});

test('global search filter select pin css keeps pinned panels above the modal toolbar', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-gsm-filters-dropdown-panel')
        ->toContain('[data-tido-gsm-select-fixed-pinned="1"]')
        ->toContain('[data-tido-fixed-pinned="1"]')
        ->toContain('[data-tido-gsm-select-fixed-pinned="1"]')
        ->toContain('pointer-events: none')
        ->toContain('z-index: 100002;')
        ->toContain('gsm-filter-select-pin.js');
});
