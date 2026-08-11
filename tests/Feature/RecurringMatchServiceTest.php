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
