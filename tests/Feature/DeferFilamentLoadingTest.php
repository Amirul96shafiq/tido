<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('expenses list defers table records until loadTable is called', function (): void {
    $expense = Expense::factory()->create(['merchant_name' => 'Deferred Table Shop']);

    $component = Livewire::test(ListExpenses::class)
        ->assertSuccessful();

    expect($component->html())
        ->not->toContain('wire:key="'.$component->instance()->getId().'.table.records.'.$expense->getKey().'"');

    $component
        ->loadTable()
        ->assertCanSeeTableRecords([$expense]);
});

test('expenses list empty state appears after loadTable', function (): void {
    livewireDeferredListPage(ListExpenses::class)
        ->assertSuccessful()
        ->assertSee('No expenses yet');
});

test('expense edit defers notes schema until loadDeferredSchema', function (): void {
    $expense = Expense::factory()->create();

    $component = Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful();

    expect($component->html())
        ->toContain('fi-sc-loading')
        ->not->toContain('fi-notes-rich-editor');

    loadDeferredFormSchemas($component, 'expenseNotes')
        ->assertSchemaComponentExists(deferredFormComponentKey('expenseNotes', 'notes'));
});

test('expense edit defers line item labels until the item schema is loaded', function (): void {
    $expense = Expense::factory()->create();

    $item = ExpenseItem::factory()
        ->for($expense)
        ->create([
            'description' => 'Deferred Anchor Item',
        ]);

    $component = Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful();

    expect($component->html())
        ->not->toContain('id="expense-item-'.$item->getKey().'"');

    loadDeferredExpenseItemSchema($component, $item->getKey())
        ->assertSchemaComponentExists(
            deferredFormComponentKey(deferredExpenseItemSchemaKey($item->getKey()), 'label_id'),
        );
});
