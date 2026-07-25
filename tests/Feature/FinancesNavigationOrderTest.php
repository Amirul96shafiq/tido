<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Invoices\InvoiceResource;

test('finances navigation orders upload receipts invoices then budgets', function () {
    expect(ReceiptUploadPage::getNavigationSort())->toBe(1)
        ->and(InvoiceResource::getNavigationSort())->toBe(2)
        ->and(BudgetResource::getNavigationSort())->toBe(3)
        ->and(ReceiptUploadPage::getNavigationGroup())->toBe('Finances')
        ->and(InvoiceResource::getNavigationGroup())->toBe('Finances')
        ->and(BudgetResource::getNavigationGroup())->toBe('Finances');
});
