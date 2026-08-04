<?php

declare(strict_types=1);

test('resource view slide-overs use blurred modal overlay hook', function () {
    $tables = [
        app_path('Filament/Resources/Invoices/Tables/InvoicesTable.php'),
        app_path('Filament/Resources/Labels/Tables/LabelsTable.php'),
        app_path('Filament/Resources/Budgets/Tables/BudgetsTable.php'),
        app_path('Filament/Resources/PaymentMethods/Tables/PaymentMethodsTable.php'),
        app_path('Filament/Resources/FamilyMembers/Tables/FamilyMembersTable.php'),
    ];

    foreach ($tables as $path) {
        $source = (string) file_get_contents($path);

        expect($source)
            ->toContain("extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true)")
            ->toContain('->slideOver()');
    }
});

test('filament modal close overlays apply backdrop blur', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $overlayBlock = Str::between(
        $css,
        '/* Shared modal overlay blur',
        '/* Database notifications slide-over',
    );

    expect($overlayBlock)
        ->toContain('.fi-modal-close-overlay')
        ->toContain('@apply backdrop-blur-md;');
});

test('open filament modals lift above sticky form actions and panel chrome', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-layout:has(.fi-modal.fi-modal-open:not([style*="display: none"]))')
        ->toContain('.fi-modal.fi-modal-open:not([style*="display: none"])')
        ->toContain('z-index: 35;')
        ->toContain('z-index: 15;');
});

test('open topbar action modals lift above sidebar and user menu dropdown', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $block = Str::between(
        $css,
        '/* Topbar-hosted action modals',
        '.fi-fo-file-upload-editor {',
    );

    expect($block)
        ->toContain('.fi-topbar-ctn:has(.fi-modal.fi-modal-open:not([style*="display: none"]))')
        ->toContain('z-index: 60 !important;');
});

test('open filament modal layers beat raised dropdown panels', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-dropdown-panel')
        ->toContain('z-index: 50;')
        ->toContain('.fi-modal.fi-modal-open > .fi-modal-close-overlay')
        ->toContain('.fi-modal.fi-modal-open > .fi-modal-window-ctn')
        ->toContain('z-index: 60;')
        ->toContain('.fi-modal.fi-modal-open ~ .fi-modal.fi-modal-open > .fi-modal-close-overlay')
        ->toContain('z-index: 70;');
});

test('file upload editor paints above sticky edit page controls', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $block = Str::between(
        $css,
        '/* File upload image editor',
        '/* Version-icon style',
    );

    expect($block)
        ->toContain('.fi-fo-file-upload-editor')
        ->toContain('z-index: 50 !important;')
        ->toContain('.tido-sticky-scope')
        ->toContain(':has(.tido-sticky-marker)')
        ->toContain('z-index: -1;');
});
