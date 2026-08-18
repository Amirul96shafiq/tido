<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
use App\Models\Label;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
        'is_shared' => true,
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

test('personal budget spent counts only the assignee expenses', function () {
    $label = Label::factory()->create();
    $member = FamilyMember::factory()->create();

    $personal = Budget::factory()->forFamilyMember($member)->create([
        'label_id' => $label->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
    ]);

    $primaryExpense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    $memberExpense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
        'family_member_id' => $member->id,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $primaryExpense->id,
        'label_id' => $label->id,
        'line_total' => 40.00,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $memberExpense->id,
        'label_id' => $label->id,
        'line_total' => 60.00,
    ]);

    expect($personal->spentInPeriod())->toBe(60.0);
});

test('shared budget spent counts all household expenses', function () {
    $label = Label::factory()->create();
    $member = FamilyMember::factory()->create();

    $shared = Budget::factory()->shared()->create([
        'label_id' => $label->id,
        'family_member_id' => $member->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
    ]);

    $primaryExpense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    $memberExpense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
        'family_member_id' => $member->id,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $primaryExpense->id,
        'label_id' => $label->id,
        'line_total' => 40.00,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $memberExpense->id,
        'label_id' => $label->id,
        'line_total' => 60.00,
    ]);

    expect($shared->spentInPeriod())->toBe(100.0);
});

test('applies to expense matches personal and shared ownership', function () {
    $member = FamilyMember::factory()->create();
    $other = FamilyMember::factory()->create();

    $personal = Budget::factory()->forFamilyMember($member)->create();
    $shared = Budget::factory()->forFamilyMember($member)->shared()->create();
    $primaryPersonal = Budget::factory()->create(['is_shared' => false, 'family_member_id' => null]);

    $memberExpense = Expense::factory()->create(['family_member_id' => $member->id]);
    $otherExpense = Expense::factory()->create(['family_member_id' => $other->id]);
    $primaryExpense = Expense::factory()->create(['family_member_id' => null]);

    expect($personal->appliesToExpense($memberExpense))->toBeTrue()
        ->and($personal->appliesToExpense($otherExpense))->toBeFalse()
        ->and($personal->appliesToExpense($primaryExpense))->toBeFalse()
        ->and($shared->appliesToExpense($otherExpense))->toBeTrue()
        ->and($primaryPersonal->appliesToExpense($primaryExpense))->toBeTrue()
        ->and($primaryPersonal->appliesToExpense($memberExpense))->toBeFalse();
});

test('visible to scope shows owned or shared budgets for family members', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $other = FamilyMember::factory()->create();
    $loginUser = $member->loginUser;

    $owned = Budget::factory()->forFamilyMember($member)->create(['title' => 'Mine']);
    $shared = Budget::factory()->shared()->create([
        'title' => 'Household',
        'family_member_id' => null,
    ]);
    $hidden = Budget::factory()->forFamilyMember($other)->create(['title' => 'Other personal']);

    $visibleIds = Budget::query()
        ->visibleTo($loginUser)
        ->pluck('id')
        ->all();

    expect($visibleIds)->toContain($owned->id, $shared->id)
        ->and($visibleIds)->not->toContain($hidden->id);
});

test('spent totals for batches matching spent in period', function () {
    $label = Label::factory()->create();
    $otherLabel = Label::factory()->create();
    $member = FamilyMember::factory()->create();

    $personal = Budget::factory()->forFamilyMember($member)->create([
        'label_id' => $label->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
    ]);

    $shared = Budget::factory()->shared()->create([
        'label_id' => $label->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
    ]);

    $overall = Budget::factory()->create([
        'title' => null,
        'label_id' => null,
        'amount' => 1000.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'is_shared' => true,
    ]);

    $primaryExpense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    $memberExpense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
        'family_member_id' => $member->id,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $primaryExpense->id,
        'label_id' => $label->id,
        'line_total' => 40.00,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $memberExpense->id,
        'label_id' => $label->id,
        'line_total' => 60.00,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $primaryExpense->id,
        'label_id' => $otherLabel->id,
        'line_total' => 25.00,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $totals = Budget::spentTotalsFor(collect([$personal, $shared, $overall]));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBe(1)
        ->and($totals[$personal->id])->toBe(60.0)
        ->and($totals[$shared->id])->toBe(100.0)
        ->and($totals[$overall->id])->toBe(125.0);
});

test('spent for preview calculates matching expenses on an unsaved budget', function () {
    $label = Label::factory()->create();

    $expense = Expense::factory()->create([
        'date_time' => now(),
        'status' => 'parsed',
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'line_total' => 80.00,
    ]);

    $preview = new Budget([
        'label_id' => $label->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'is_shared' => true,
    ]);

    expect(Budget::spentForPreview($preview))->toBe(80.0);
});
