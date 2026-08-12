<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Services\RecurringMatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    Expense::unsetEventDispatcher();
});

test('matches cursor alias anysphere within due window', function () {
    $label = Label::factory()->create();
    $recurring = Recurring::factory()->create([
        'title' => 'Cursor',
        'label_id' => $label->id,
        'merchant_aliases' => ['Cursor', 'Anysphere'],
        'family_member_id' => null,
        'is_shared' => false,
    ]);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-08',
        'status' => RecurringOccurrenceStatus::Due,
        'expected_amount' => 84.79,
    ]);

    $expense = Expense::factory()->create([
        'merchant_name' => 'Anysphere, Inc.',
        'total_amount' => 84.79,
        'date_time' => '2026-08-08 09:00:00',
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    ExpenseItem::factory()->create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'line_total' => 84.79,
    ]);

    $matched = app(RecurringMatchService::class)->matchExpense($expense);

    expect($matched)->not->toBeNull()
        ->and($matched->id)->toBe($occurrence->id)
        ->and($matched->fresh()->status)->toBe(RecurringOccurrenceStatus::Completed)
        ->and((float) $matched->fresh()->actual_amount)->toBe(84.79);
});

test('does not match wrong owner', function () {
    $familyMember = FamilyMember::factory()->create();
    $recurring = Recurring::factory()->forFamilyMember($familyMember)->create([
        'merchant_aliases' => ['PTPTN'],
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-05',
        'status' => RecurringOccurrenceStatus::Due,
    ]);

    $expense = Expense::factory()->create([
        'merchant_name' => 'PTPTN',
        'date_time' => '2026-08-05 09:00:00',
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    $matched = app(RecurringMatchService::class)->matchExpense($expense);

    expect($matched)->toBeNull();
});

test('shared recurring matches any household expense', function () {
    $recurring = Recurring::factory()->shared()->create([
        'merchant_aliases' => ['GPROP'],
        'family_member_id' => null,
    ]);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-05',
        'status' => RecurringOccurrenceStatus::Due,
    ]);

    $familyMember = FamilyMember::factory()->create();
    $expense = Expense::factory()->create([
        'merchant_name' => 'GPROP Monthly Bills',
        'date_time' => '2026-08-05 09:00:00',
        'status' => 'reviewed',
        'family_member_id' => $familyMember->id,
    ]);

    $matched = app(RecurringMatchService::class)->matchExpense($expense);

    expect($matched?->id)->toBe($occurrence->id);
});

test('completing occurrence does not double complete', function () {
    $recurring = Recurring::factory()->create([
        'merchant_aliases' => ['Cursor'],
        'instalment_total' => 3,
        'instalment_remaining' => 3,
    ]);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-08',
        'status' => RecurringOccurrenceStatus::Due,
    ]);

    $expense = Expense::factory()->create([
        'merchant_name' => 'Cursor',
        'date_time' => '2026-08-08 09:00:00',
        'status' => 'parsed',
        'total_amount' => 80,
    ]);

    $service = app(RecurringMatchService::class);
    $service->completeOccurrence($occurrence, $expense);
    $service->completeOccurrence($occurrence->fresh(), $expense);

    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Completed)
        ->and($recurring->fresh()->instalment_remaining)->toBe(2);
});

test('skips occurrence and decrements instalments', function () {
    $recurring = Recurring::factory()->create([
        'instalment_total' => 2,
        'instalment_remaining' => 2,
    ]);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
    ]);

    app(RecurringMatchService::class)->skipOccurrence($occurrence);

    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Skipped)
        ->and($recurring->fresh()->instalment_remaining)->toBe(1);
});

test('revertOccurrence restores status and increments instalments', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');

    $recurring = Recurring::factory()->create([
        'instalment_total' => 2,
        'instalment_remaining' => 1,
    ]);

    $occurrence = RecurringOccurrence::factory()->skipped()->create([
        'recurring_id' => $recurring->id,
        'due_on' => now()->toDateString(),
    ]);

    app(RecurringMatchService::class)->revertOccurrence($occurrence);

    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Due)
        ->and($recurring->fresh()->instalment_remaining)->toBe(2);
});

test('matchParsedExpenses completes open dues from existing receipts', function () {
    $gprop = Recurring::factory()->create([
        'title' => 'GPROP Monthly Bills',
        'merchant_aliases' => ['GPROP'],
    ]);
    $cursor = Recurring::factory()->create([
        'title' => 'Cursor',
        'merchant_aliases' => ['Cursor', 'Anysphere'],
    ]);
    $tnb = Recurring::factory()->create([
        'title' => 'TNB Electricity',
        'merchant_aliases' => ['Tenaga', 'myTNB', 'TNB'],
    ]);

    $gpropOccurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $gprop->id,
        'due_on' => '2026-08-05',
        'status' => RecurringOccurrenceStatus::Overdue,
    ]);
    $cursorOccurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $cursor->id,
        'due_on' => '2026-08-08',
        'status' => RecurringOccurrenceStatus::Overdue,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $tnb->id,
        'due_on' => '2026-08-05',
        'status' => RecurringOccurrenceStatus::Overdue,
    ]);

    Expense::factory()->create([
        'merchant_name' => 'GPROP Monthly Bills',
        'total_amount' => 199.14,
        'date_time' => '2026-08-05 20:53:20',
        'status' => 'reviewed',
        'family_member_id' => null,
    ]);
    Expense::factory()->create([
        'merchant_name' => 'Anysphere, Inc.',
        'total_amount' => 84.79,
        'date_time' => '2026-08-08 00:00:00',
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    $result = app(RecurringMatchService::class)->matchParsedExpenses();

    expect($result['matched'])->toBe(2)
        ->and($gpropOccurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Completed)
        ->and((float) $gpropOccurrence->fresh()->actual_amount)->toBe(199.14)
        ->and($cursorOccurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Completed)
        ->and((float) $cursorOccurrence->fresh()->actual_amount)->toBe(84.79);
});

test('matchParsedExpenses dry run does not write', function () {
    $recurring = Recurring::factory()->create([
        'merchant_aliases' => ['PTPTN'],
    ]);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-05',
        'status' => RecurringOccurrenceStatus::Due,
    ]);

    Expense::factory()->create([
        'merchant_name' => 'PTPTN',
        'total_amount' => 50,
        'date_time' => '2026-08-05 20:47:31',
        'status' => 'reviewed',
    ]);

    $result = app(RecurringMatchService::class)->matchParsedExpenses(dryRun: true);

    expect($result['matched'])->toBe(1)
        ->and($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Due)
        ->and($occurrence->fresh()->expense_id)->toBeNull();
});
