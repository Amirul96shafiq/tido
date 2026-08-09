<?php

declare(strict_types=1);

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('edit budget title appends Budget model label and highlights record title', function () {
    $budget = Budget::factory()->create([
        'title' => null,
        'label_id' => null,
        'period' => 'monthly',
        'year' => 2026,
    ]);

    $page = Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()]);
    $titleHtml = (string) $page->instance()->getTitle();

    expect(html_entity_decode(strip_tags($titleHtml)))
        ->toBe('Edit Overall Budget · Monthly 2026 Budget')
        ->and($titleHtml)
        ->toContain('text-primary-600 dark:text-primary-400')
        ->toContain('<span class="text-primary-600 dark:text-primary-400">Overall Budget · Monthly 2026</span>')
        ->toEndWith(BudgetResource::getTitleCaseModelLabel());
});

test('edit label title appends Label model label and highlights record title', function () {
    $label = Label::factory()->create(['name' => 'Food & Dining']);

    $page = Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()]);
    $titleHtml = (string) $page->instance()->getTitle();

    expect(html_entity_decode(strip_tags($titleHtml)))
        ->toBe('Edit Food & Dining Label')
        ->and($titleHtml)
        ->toContain('text-primary-600 dark:text-primary-400')
        ->toContain('<span class="text-primary-600 dark:text-primary-400">Food &amp; Dining</span>')
        ->toEndWith(LabelResource::getTitleCaseModelLabel());
});

test('edit expense title appends Expense model label and highlights record title', function () {
    $expense = Expense::factory()->create(['merchant_name' => 'Starbucks']);
    $recordTitle = 'Starbucks #'.$expense->getKey();

    $page = Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()]);
    $titleHtml = (string) $page->instance()->getTitle();

    expect(html_entity_decode(strip_tags($titleHtml)))
        ->toBe('Edit '.$recordTitle.' Expense')
        ->and($titleHtml)
        ->toContain('text-primary-600 dark:text-primary-400')
        ->toContain('<span class="text-primary-600 dark:text-primary-400">'.$recordTitle.'</span>')
        ->toEndWith(ExpenseResource::getTitleCaseModelLabel());
});
