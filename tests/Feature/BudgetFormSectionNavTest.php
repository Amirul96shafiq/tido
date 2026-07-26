<?php

declare(strict_types=1);

use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Schemas\BudgetForm;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('budget create page renders sticky section nav markers', function () {
    Livewire::test(CreateBudget::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false)
        ->assertSee('tido-section-nav', false);
});

test('budget edit page renders sticky section nav markers', function () {
    $budget = Budget::factory()->create();

    Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('budget create section nav omits performance tab', function () {
    Livewire::test(CreateBudget::class)
        ->assertSuccessful()
        ->assertSee('Budget Appearance')
        ->assertSee('Limit &amp; Period', false)
        ->assertSee('Budget Settings')
        ->assertSee('Alert Settings')
        ->assertSee('Budget Notes')
        ->assertDontSee('#budget-performance', false);
});

test('budget edit section nav includes performance tab', function () {
    $budget = Budget::factory()->create();

    Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Budget Performance')
        ->assertSee('#budget-performance', false);
});

test('budget section nav items match sectionNavItems helper', function () {
    expect(BudgetForm::sectionNavItems(includePerformance: false))->toBe([
        ['label' => 'Budget Appearance', 'id' => 'budget-appearance'],
        ['label' => 'Limit & Period', 'id' => 'limit-period'],
        ['label' => 'Budget Settings', 'id' => 'budget-settings'],
        ['label' => 'Alert Settings', 'id' => 'alert-settings'],
        ['label' => 'Budget Notes', 'id' => 'budget-notes'],
    ])->and(BudgetForm::sectionNavItems(includePerformance: true))->toBe([
        ['label' => 'Budget Appearance', 'id' => 'budget-appearance'],
        ['label' => 'Limit & Period', 'id' => 'limit-period'],
        ['label' => 'Budget Settings', 'id' => 'budget-settings'],
        ['label' => 'Alert Settings', 'id' => 'alert-settings'],
        ['label' => 'Budget Notes', 'id' => 'budget-notes'],
        ['label' => 'Budget Performance', 'id' => 'budget-performance'],
    ]);
});

test('budget section nav smooth scrolls on tab click', function () {
    Livewire::test(CreateBudget::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
