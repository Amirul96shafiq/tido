<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Enums\RecurringType;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('goal target derives instalment total from expected amount', function () {
    $recurring = Recurring::factory()->withGoal(600, 50)->create([
        'instalment_total' => null,
        'instalment_remaining' => null,
    ]);

    expect($recurring->fresh()->instalment_total)->toBe(12)
        ->and($recurring->fresh()->instalment_remaining)->toBe(12)
        ->and($recurring->fresh()->type)->toBe(RecurringType::TransferInvestment);
});

test('goal progress uses completed actual amounts', function () {
    $recurring = Recurring::factory()->withGoal(600, 50)->create([
        'instalment_total' => 12,
        'instalment_remaining' => 10,
    ]);

    RecurringOccurrence::factory()->completed()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Completed,
        'actual_amount' => 50,
    ]);
    RecurringOccurrence::factory()->completed()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Completed,
        'actual_amount' => 50,
        'period_start' => now()->addMonth()->toDateString(),
    ]);

    expect($recurring->goalProgressAmount())->toBe(100.0)
        ->and($recurring->goalProgressPercent())->toBe(16.67);
});

test('goal progress adds prior contributed amount without extra occurrences', function () {
    $recurring = Recurring::factory()->withGoal(500, 50)->create([
        'prior_contributed_amount' => 150,
    ]);

    expect($recurring->goalProgressAmount())->toBe(150.0)
        ->and($recurring->goalProgressPercent())->toBe(30.0)
        ->and($recurring->fresh()->instalment_remaining)->toBe(7);
});
