<?php

declare(strict_types=1);

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('edit budget title appends Budget model label', function () {
    $budget = Budget::factory()->create([
        'title' => null,
        'label_id' => null,
        'period' => 'monthly',
        'year' => 2026,
    ]);

    $page = Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()]);

    expect((string) $page->instance()->getTitle())
        ->toBe('Edit Overall Budget · Monthly 2026 Budget')
        ->and((string) $page->instance()->getTitle())
        ->toEndWith(BudgetResource::getTitleCaseModelLabel());
});

test('edit label title appends Label model label', function () {
    $label = Label::factory()->create(['name' => 'Food & Dining']);

    $page = Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()]);

    expect((string) $page->instance()->getTitle())
        ->toBe('Edit Food & Dining Label')
        ->and((string) $page->instance()->getTitle())
        ->toEndWith(LabelResource::getTitleCaseModelLabel());
});

test('edit invoice title appends Invoice model label', function () {
    $invoice = Invoice::factory()->create(['merchant_name' => 'Starbucks']);

    $page = Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()]);

    expect((string) $page->instance()->getTitle())
        ->toBe('Edit Starbucks Invoice')
        ->and((string) $page->instance()->getTitle())
        ->toEndWith(InvoiceResource::getTitleCaseModelLabel());
});
