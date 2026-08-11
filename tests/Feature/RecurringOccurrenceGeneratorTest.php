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
