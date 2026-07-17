<?php

declare(strict_types=1);

use App\Filament\Widgets\BudgetStatus;
use App\Models\Budget;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('budget status widget renders empty state', function () {
    Livewire::test(BudgetStatus::class)
        ->assertSuccessful()
        ->assertSee('No budgets yet')
        ->assertSee('Create a budget to track spending against a limit.')
        ->assertSee('New budget');
});

test('budget status widget renders active budgets', function () {
    $label = Label::factory()->create(['name' => 'Groceries']);

    Budget::factory()->create([
        'label_id' => $label->id,
        'amount' => 500.00,
        'is_active' => true,
    ]);

    Livewire::test(BudgetStatus::class)
        ->assertSuccessful()
        ->assertSee('Groceries')
        ->assertDontSee('No budgets yet');
});
