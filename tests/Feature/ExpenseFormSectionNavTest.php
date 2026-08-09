<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('invoice create page renders sticky section nav markers', function () {
    Livewire::test(CreateExpense::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false)
        ->assertSee('tido-section-nav', false);
});

test('invoice edit page renders sticky section nav markers', function () {
    $invoice = Expense::factory()->create();

    Livewire::test(EditExpense::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('invoice section nav lists anchor tabs', function () {
    Livewire::test(CreateExpense::class)
        ->assertSuccessful()
        ->assertSee('Image &amp; Uploads', false)
        ->assertSee('Receipt Details')
        ->assertSee('Expense Notes')
        ->assertSee('Line Items')
        ->assertSee('Expense Status')
        ->assertSee('#image-uploads', false)
        ->assertSee('#receipt-details', false)
        ->assertSee('#expense-notes', false)
        ->assertSee('#line-items', false)
        ->assertSee('#expense-status', false);
});

test('invoice section nav items match sectionNavItems helper', function () {
    expect(ExpenseForm::sectionNavItems())->toBe([
        ['label' => 'Image & Uploads', 'id' => 'image-uploads'],
        ['label' => 'Receipt Details', 'id' => 'receipt-details'],
        ['label' => 'Expense Notes', 'id' => 'expense-notes'],
        ['label' => 'Line Items', 'id' => 'line-items'],
        ['label' => 'Expense Status', 'id' => 'expense-status'],
    ]);
});

test('invoice section nav smooth scrolls on tab click', function () {
    Livewire::test(CreateExpense::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
