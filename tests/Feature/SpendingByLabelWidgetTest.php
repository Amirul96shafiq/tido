<?php

declare(strict_types=1);

use App\Filament\Widgets\SpendingByLabel;
use App\Models\Expense;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('spending by label widget renders with enriched chart data', function () {
    Expense::unsetEventDispatcher();

    $label = Label::factory()->create(['name' => 'Groceries', 'slug' => 'groceries']);

    $expense = Expense::factory()->create([
        'merchant_name' => 'Grocery Store',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 55.00,
    ]);

    $expense->expenseItems()->create([
        'label_id' => $label->id,
        'description' => 'Vegetables',
        'quantity' => 1,
        'unit_price' => 55.00,
        'line_total' => 55.00,
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(SpendingByLabel::class)
        ->assertSuccessful()
        ->assertSee("pointStyle: 'circle'", false)
        ->assertSee('pointStyleWidth: 14', false)
        ->assertSee('boxHeight: 10', false);
});

test('spending by label widget renders empty state', function () {
    Livewire::test(SpendingByLabel::class)
        ->assertSuccessful()
        ->assertSee('No expenses')
        ->assertSee('No label spending recorded for this month.')
        ->assertSee('Upload Receipts');
});

test('spending by label widget listens for echo expense updates without polling', function () {
    $component = Livewire::test(SpendingByLabel::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.30s')
        ->assertDontSeeHtml('wire:poll.5s');

    expect($component->instance()->getListeners())
        ->toHaveKey('echo-private:household.expenses,.ExpenseUpdated');
});
