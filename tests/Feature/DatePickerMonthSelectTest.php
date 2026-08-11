<?php

declare(strict_types=1);

test('date picker month select script builds a filament-styled month dropdown', function (): void {
    $js = (string) file_get_contents(resource_path('js/date-picker-month-select.js'));

    expect($js)
        ->toContain('.fi-fo-date-time-picker-month-select')
        ->toContain('tido-date-picker-month')
        ->toContain('fi-dropdown-panel')
        ->toContain('fi-dropdown-list-item')
        ->toContain('focusedMonth');
});

test('date picker month select styles hide the native select popup', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-date-picker-month-select-native')
        ->toContain('.tido-date-picker-month-panel')
        ->toContain('.tido-date-picker-month-trigger')
        ->toContain('fi-dropdown-list-item.fi-selected');
});

test('date picker month select is registered on the admin panel', function (): void {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $vite = (string) file_get_contents(base_path('vite.config.js'));

    expect($provider)
        ->toContain('date-picker-month-select')
        ->toContain('resources/js/date-picker-month-select.js');

    expect($vite)->toContain('resources/js/date-picker-month-select.js');
});
