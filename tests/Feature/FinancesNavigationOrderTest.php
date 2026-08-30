<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Expenses\ExpenseResource;

test('finances navigation orders add receipts expenses then budgets', function () {
    expect(ReceiptUploadPage::getNavigationSort())->toBe(1)
        ->and(ExpenseResource::getNavigationSort())->toBe(2)
        ->and(BudgetResource::getNavigationSort())->toBe(3)
        ->and(ReceiptUploadPage::getNavigationGroup())->toBe('Finances')
        ->and(ExpenseResource::getNavigationGroup())->toBe('Finances')
        ->and(BudgetResource::getNavigationGroup())->toBe('Finances');
});
