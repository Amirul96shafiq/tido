<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringDuplicator;
use App\Support\RecurringFormNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create([
        'household_role' => HouseholdRole::Primary,
    ]));
});

test('normalizer converts prior transfer count into contributed amount', function () {
    $data = RecurringFormNormalizer::normalize([
        'title' => 'Tabung',
        'type' => RecurringType::TransferInvestment->value,
        'tracking_mode' => 'target_amount',
        'expected_amount' => 50,
        'goal_target_amount' => 500,
        'prior_contribution_mode' => 'count',
        'prior_transfer_count' => 3,
        'cadence_preset' => 'monthly',
        'anchor_day' => 5,
        'starts_on' => '2026-08-01',
        'responsibility' => 'primary',
    ]);

    expect($data)->not->toHaveKeys(['prior_contribution_mode', 'prior_transfer_count', 'tracking_mode'])
        ->and($data['prior_contributed_amount'])->toBe(150.0);
});

test('normalizer keeps prior amount mode and strips ui keys', function () {
    $data = RecurringFormNormalizer::normalize([
        'title' => 'Tabung',
        'type' => RecurringType::TransferInvestment->value,
        'tracking_mode' => 'target_amount',
        'expected_amount' => 50,
        'goal_target_amount' => 500,
        'prior_contribution_mode' => 'amount',
        'prior_contributed_amount' => 125.5,
        'prior_transfer_count' => 9,
        'cadence_preset' => 'monthly',
        'anchor_day' => 5,
        'starts_on' => '2026-08-01',
        'responsibility' => 'primary',
    ]);

    expect($data)->not->toHaveKeys(['prior_contribution_mode', 'prior_transfer_count'])
        ->and($data['prior_contributed_amount'])->toBe(125.5);
});

test('hydrateVirtualFields sets prior contribution mode from stored amount', function () {
    $data = RecurringFormNormalizer::hydrateVirtualFields([
        'goal_target_amount' => 500,
        'prior_contributed_amount' => 100,
        'expected_amount' => 50,
        'frequency' => 'repeating',
        'interval_months' => 1,
    ]);

    expect($data['tracking_mode'])->toBe('target_amount')
        ->and($data['prior_contribution_mode'])->toBe('amount')
        ->and($data['prior_contributed_amount'])->toBe(100.0);
});

test('normalizer clears prior outside target amount mode', function () {
    $data = RecurringFormNormalizer::normalize([
        'title' => 'ASNB',
        'type' => RecurringType::TransferInvestment->value,
        'tracking_mode' => 'open_ended',
        'expected_amount' => 50,
        'goal_target_amount' => 500,
        'prior_contribution_mode' => 'amount',
        'prior_contributed_amount' => 150,
        'cadence_preset' => 'monthly',
        'anchor_day' => 5,
        'starts_on' => '2026-08-01',
        'responsibility' => 'primary',
    ]);

    expect($data['prior_contributed_amount'])->toBeNull()
        ->and($data['goal_target_amount'])->toBeNull();
});

test('goal progress includes prior contributed amount', function () {
    $recurring = Recurring::factory()->withGoal(500, 50)->create([
        'prior_contributed_amount' => 100,
    ]);

    RecurringOccurrence::factory()->completed()->create([
        'recurring_id' => $recurring->id,
        'actual_amount' => 50,
    ]);

    expect($recurring->goalProgressAmount())->toBe(150.0)
        ->and($recurring->goalProgressPercent())->toBe(30.0);
});

test('target amount save refreshes stale instalment total from goal', function () {
    $recurring = Recurring::factory()->withGoal(500, 50)->create([
        'instalment_total' => 12,
        'instalment_remaining' => 11,
    ]);

    expect($recurring->fresh()->instalment_total)->toBe(10)
        ->and($recurring->fresh()->instalment_remaining)->toBe(10);
});

test('create target amount with prior count derives remaining slots', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Tabung Raya',
            'type' => RecurringType::TransferInvestment->value,
            'tracking_mode' => 'target_amount',
            'expected_amount' => 50,
            'goal_target_amount' => 500,
            'prior_contribution_mode' => 'count',
            'prior_transfer_count' => 3,
            'cadence_preset' => 'monthly',
            'anchor_day' => 5,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => true,
            'notify_filament' => true,
            'notify_whatsapp' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Tabung Raya')->first();

    expect($recurring)->not->toBeNull()
        ->and((float) $recurring->prior_contributed_amount)->toBe(150.0)
        ->and($recurring->instalment_total)->toBe(10)
        ->and($recurring->instalment_remaining)->toBe(7)
        ->and($recurring->goalProgressAmount())->toBe(150.0);
});

test('edit target amount remaining subtracts prior and completed occurrences', function () {
    $recurring = Recurring::factory()->withGoal(500, 50)->create([
        'title' => 'Tabung Edit',
        'prior_contributed_amount' => null,
    ]);

    RecurringOccurrence::factory()->completed()->create([
        'recurring_id' => $recurring->id,
        'actual_amount' => 50,
        'period_start' => now()->subMonths(2)->toDateString(),
        'due_on' => now()->subMonths(2)->toDateString(),
    ]);
    RecurringOccurrence::factory()->skipped()->create([
        'recurring_id' => $recurring->id,
        'period_start' => now()->subMonth()->toDateString(),
        'due_on' => now()->subMonth()->toDateString(),
    ]);

    Livewire::test(EditRecurring::class, ['record' => $recurring->getRouteKey()])
        ->fillForm([
            'tracking_mode' => 'target_amount',
            'expected_amount' => 50,
            'goal_target_amount' => 500,
            'prior_contribution_mode' => 'amount',
            'prior_contributed_amount' => 100,
            'cadence_preset' => 'monthly',
            'responsibility' => 'primary',
            'is_active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $recurring->fresh();

    // total 10 − completed 1 − skipped 1 − priorSlots 2 = 6
    expect((float) $fresh->prior_contributed_amount)->toBe(100.0)
        ->and($fresh->instalment_total)->toBe(10)
        ->and($fresh->instalment_remaining)->toBe(6)
        ->and($fresh->goalProgressAmount())->toBe(150.0);
});

test('duplicate clears prior contributed amount', function () {
    $source = Recurring::factory()->withGoal(500, 50)->create([
        'title' => 'Tabung Dup',
        'prior_contributed_amount' => 150,
    ]);

    expect($source->fresh()->instalment_remaining)->toBe(7);

    $replica = app(RecurringDuplicator::class)->duplicate($source);

    expect($replica->prior_contributed_amount)->toBeNull()
        ->and($replica->instalment_total)->toBe(10)
        ->and($replica->instalment_remaining)->toBe(10)
        ->and($replica->goalProgressAmount())->toBe(0.0);
});
