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
