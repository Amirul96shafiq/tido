<?php

declare(strict_types=1);

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Services\RecurringOccurrenceGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('generates monthly occurrence idempotently', function () {
    Carbon::setTestNow('2026-08-10 10:00:00');

    $recurring = Recurring::factory()->create([
        'frequency' => RecurringFrequency::Repeating,
        'interval_months' => 1,
        'anchor_day' => 5,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-08-05',
    ]);

    $generator = app(RecurringOccurrenceGenerator::class);

    $first = $generator->generateFor($recurring->fresh());
    $countAfterFirst = RecurringOccurrence::query()->where('recurring_id', $recurring->id)->count();
    $second = $generator->generateFor($recurring->fresh());

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe(0)
        ->and(RecurringOccurrence::query()->where('recurring_id', $recurring->id)->count())->toBe($countAfterFirst);
});

test('generates quarterly and yearly intervals', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $quarterly = Recurring::factory()->create([
        'interval_months' => 3,
        'anchor_day' => 1,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-08-01',
    ]);

    $yearly = Recurring::factory()->create([
        'interval_months' => 12,
        'anchor_day' => 1,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-08-01',
    ]);

    $generator = app(RecurringOccurrenceGenerator::class);
    $generator->generateFor($quarterly->fresh());
    $generator->generateFor($yearly->fresh());

    expect($quarterly->fresh()->next_due_on?->toDateString())->toBe('2026-11-01')
        ->and($yearly->fresh()->next_due_on?->toDateString())->toBe('2027-08-01');
});

test('once cadence creates a single occurrence', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $recurring = Recurring::factory()->once()->create([
        'anchor_day' => 15,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-08-15',
    ]);

    $generator = app(RecurringOccurrenceGenerator::class);
    $generator->generateFor($recurring->fresh());
    $generator->generateFor($recurring->fresh());

    expect(RecurringOccurrence::query()->where('recurring_id', $recurring->id)->count())->toBe(1)
        ->and($recurring->fresh()->next_due_on)->toBeNull();
});

test('refreshStatuses marks overdue occurrences', function () {
    Carbon::setTestNow('2026-08-20 10:00:00');

    $occurrence = RecurringOccurrence::factory()->create([
        'due_on' => '2026-08-10',
        'status' => RecurringOccurrenceStatus::Due,
    ]);

    $updated = app(RecurringOccurrenceGenerator::class)->refreshStatuses();

    expect($updated)->toBeGreaterThan(0)
        ->and($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Overdue);
});

test('initial due date never precedes starts on when due day has passed', function () {
    $recurring = Recurring::factory()->make([
        'frequency' => RecurringFrequency::Repeating,
        'interval_months' => 1,
        'anchor_day' => 5,
        'starts_on' => '2026-08-20',
        'next_due_on' => null,
    ]);

    expect($recurring->resolveInitialDueOn()?->toDateString())->toBe('2026-09-05');
});

test('initial due date uses due day in start month when still ahead', function () {
    $recurring = Recurring::factory()->make([
        'frequency' => RecurringFrequency::Repeating,
        'interval_months' => 1,
        'anchor_day' => 25,
        'starts_on' => '2026-08-20',
        'next_due_on' => null,
    ]);

    expect($recurring->resolveInitialDueOn()?->toDateString())->toBe('2026-08-25');
});

test('creating recurring derives first due on or after starts on', function () {
    $recurring = Recurring::factory()->create([
        'frequency' => RecurringFrequency::Repeating,
        'interval_months' => 1,
        'anchor_day' => 5,
        'starts_on' => '2026-08-20',
        'next_due_on' => null,
    ]);

    expect($recurring->fresh()->next_due_on?->toDateString())->toBe('2026-09-05');
});

test('generateFor prunes open occurrences whose period no longer matches cadence', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');

    $recurring = Recurring::factory()->create([
        'title' => 'Indah Water',
        'frequency' => RecurringFrequency::Repeating,
        'interval_months' => 6,
        'anchor_day' => 24,
        'starts_on' => '2026-07-24',
        'next_due_on' => '2027-01-24',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-24',
        'period_start' => '2026-08-24',
        'period_end' => '2026-09-23',
        'status' => RecurringOccurrenceStatus::Upcoming,
        'expected_amount' => 105,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-09-24',
        'period_start' => '2026-09-24',
        'period_end' => '2026-10-23',
        'status' => RecurringOccurrenceStatus::Upcoming,
        'expected_amount' => 105,
    ]);

    $completed = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-01-24',
        'period_start' => '2026-01-24',
        'period_end' => '2026-07-23',
        'status' => RecurringOccurrenceStatus::Completed,
        'expected_amount' => 105,
        'actual_amount' => 105,
    ]);

    $created = app(RecurringOccurrenceGenerator::class)->generateFor($recurring->fresh());

    expect($created)->toBe(0)
        ->and(RecurringOccurrence::query()->where('recurring_id', $recurring->id)->open()->count())->toBe(0)
        ->and($completed->fresh())->not->toBeNull()
        ->and($recurring->fresh()->next_due_on?->toDateString())->toBe('2027-01-24');
});

test('adjusting next due discards earlier open occurrences', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');

    $recurring = Recurring::factory()->create([
        'frequency' => RecurringFrequency::Repeating,
        'interval_months' => 1,
        'anchor_day' => 24,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-10-24',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-24',
        'period_start' => '2026-08-24',
        'period_end' => '2026-09-23',
        'status' => RecurringOccurrenceStatus::Upcoming,
    ]);

    $discarded = app(RecurringOccurrenceGenerator::class)
        ->discardOpenOccurrencesBeforeNextDue($recurring->fresh());

    expect($discarded)->toBe(1)
        ->and(RecurringOccurrence::query()->where('recurring_id', $recurring->id)->open()->count())->toBe(0);
});

test('generateFor prunes open occurrences with period bounds that no longer match interval', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');

    $recurring = Recurring::factory()->create([
        'frequency' => RecurringFrequency::Repeating,
        'interval_months' => 6,
        'anchor_day' => 24,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-08-24',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-24',
        'period_start' => '2026-08-24',
        'period_end' => '2027-08-23',
        'status' => RecurringOccurrenceStatus::Upcoming,
        'expected_amount' => 222.30,
    ]);

    $created = app(RecurringOccurrenceGenerator::class)->generateFor($recurring->fresh());

    $occurrence = RecurringOccurrence::query()
        ->where('recurring_id', $recurring->id)
        ->open()
        ->first();

    expect($created)->toBe(1)
        ->and($occurrence)->not->toBeNull()
        ->and($occurrence?->due_on?->toDateString())->toBe('2026-08-24')
        ->and($occurrence?->period_end?->toDateString())->toBe('2027-02-23')
        ->and($recurring->fresh()->next_due_on?->toDateString())->toBe('2027-02-24');
});

test('generateFor syncs the template amount onto existing open occurrences', function () {
    Carbon::setTestNow('2026-08-18 10:00:00');

    $recurring = Recurring::factory()->shared()->create([
        'expected_amount' => 1.00,
        'interval_months' => 1,
        'anchor_day' => 5,
        'starts_on' => '2026-09-05',
        'next_due_on' => '2026-10-05',
    ]);

    $open = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => '2026-09-05',
        'period_start' => '2026-09-05',
        'period_end' => '2026-10-04',
        'expected_amount' => 0,
    ]);

    $completed = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Completed,
        'due_on' => '2026-08-05',
        'period_start' => '2026-08-05',
        'period_end' => '2026-09-04',
        'expected_amount' => 0,
        'actual_amount' => 0,
    ]);

    app(RecurringOccurrenceGenerator::class)->generateFor($recurring->fresh());

    expect((float) $open->fresh()->expected_amount)->toBe(1.0)
        ->and((float) $completed->fresh()->expected_amount)->toBe(0.0);
});
