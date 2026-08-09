<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Label;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('display title falls back to label name then overall', function () {
    $label = Label::factory()->create(['name' => 'Transport']);

    $withTitle = Budget::factory()->create([
        'title' => 'Commute Cap',
        'label_id' => $label->id,
    ]);

    $withLabel = Budget::factory()->create([
        'title' => null,
        'label_id' => $label->id,
    ]);

    $overall = Budget::factory()->create([
        'title' => null,
        'label_id' => null,
    ]);

    expect($withTitle->display_title)->toBe('Commute Cap')
        ->and($withLabel->display_title)->toBe('Transport')
        ->and($overall->display_title)->toBe('Overall Budget');
});

test('display icon falls back to label icon then banknotes', function () {
    $label = Label::factory()->create(['icon' => 'heroicon-o-truck']);

    $custom = Budget::factory()->create([
        'icon' => 'heroicon-o-heart',
        'label_id' => $label->id,
    ]);

    $fromLabel = Budget::factory()->create([
        'icon' => null,
        'label_id' => $label->id,
    ]);

    $overall = Budget::factory()->create([
        'icon' => null,
        'label_id' => null,
    ]);

    expect($custom->display_icon)->toBe('heroicon-o-heart')
        ->and($fromLabel->display_icon)->toBe('heroicon-o-truck')
        ->and($overall->display_icon)->toBe('heroicon-o-banknotes');
});

test('spent in period sums parsed expense items for the budget label', function () {
    $label = Label::factory()->create();
    $otherLabel = Label::factory()->create();

    $budget = Budget::factory()->create([
        'label_id' => $label->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
    ]);

    $expense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'line_total' => 80.00,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $expense->id,
        'label_id' => $otherLabel->id,
        'line_total' => 40.00,
    ]);

    expect($budget->spentInPeriod())->toBe(80.0);
});

test('spent in period excludes soft deleted expenses', function () {
    $label = Label::factory()->create();

    $budget = Budget::factory()->create([
        'label_id' => $label->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
    ]);

    $active = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
    ]);

    $trashed = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $active->id,
        'label_id' => $label->id,
        'line_total' => 100.00,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $trashed->id,
        'label_id' => $label->id,
        'line_total' => 250.00,
    ]);

    $trashed->delete();

    expect($budget->spentInPeriod())->toBe(100.0);
});
