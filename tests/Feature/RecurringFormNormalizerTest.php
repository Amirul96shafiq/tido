<?php

declare(strict_types=1);

use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Models\Recurring;
use App\Support\RecurringFormNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('normalizer maps monthly cadence and strips ui keys', function () {
    $data = RecurringFormNormalizer::normalize([
        'title' => 'Netflix',
        'type' => RecurringType::Subscription->value,
        'cadence_preset' => 'monthly',
        'anchor_day' => 8,
        'starts_on' => '2026-08-01',
        'end_rule' => 'ongoing',
        'ends_on' => '2026-12-01',
        'responsibility' => 'primary',
        'family_member_id' => 9,
        'tracking_mode' => 'open_ended',
        'due_date' => '2026-09-01',
    ]);

    expect($data)->not->toHaveKeys(['cadence_preset', 'end_rule', 'responsibility', 'tracking_mode', 'due_date'])
        ->and($data['frequency'])->toBe(RecurringFrequency::Repeating->value)
        ->and($data['interval_months'])->toBe(1)
        ->and($data['ends_on'])->toBeNull()
        ->and($data['family_member_id'])->toBeNull()
        ->and($data['is_shared'])->toBeFalse()
        ->and($data['next_due_on'])->toBe('2026-08-08');
});

test('normalizer maps once cadence from due date', function () {
    $data = RecurringFormNormalizer::normalize([
        'title' => 'Road tax',
        'type' => RecurringType::FixedBill->value,
        'cadence_preset' => 'once',
        'due_date' => '2026-09-15',
        'responsibility' => 'primary',
    ]);

    expect($data['frequency'])->toBe(RecurringFrequency::Once->value)
        ->and($data['interval_months'])->toBeNull()
        ->and($data['starts_on'])->toBe('2026-09-15')
        ->and($data['next_due_on'])->toBe('2026-09-15')
        ->and($data['anchor_day'])->toBe(15)
        ->and($data['ends_on'])->toBeNull();
});

test('normalizer preserves next due on edit', function () {
    $record = Recurring::factory()->make([
        'next_due_on' => '2026-08-05',
    ]);

    $data = RecurringFormNormalizer::normalize([
        'title' => 'Stable',
        'type' => RecurringType::Subscription->value,
        'cadence_preset' => 'monthly',
        'anchor_day' => 20,
        'starts_on' => '2026-08-20',
        'responsibility' => 'primary',
        'next_due_on' => '2099-01-01',
    ], $record);

    expect($data)->not->toHaveKey('next_due_on');
});

test('hydrateVirtualFields infers controllers from record shape', function () {
    $data = RecurringFormNormalizer::hydrateVirtualFields([
        'frequency' => RecurringFrequency::Repeating->value,
        'interval_months' => 3,
        'ends_on' => '2027-01-01',
        'is_shared' => true,
        'family_member_id' => null,
        'goal_target_amount' => 600,
        'instalment_total' => 12,
    ]);

    expect($data['cadence_preset'])->toBe('quarterly')
        ->and($data['end_rule'])->toBe('end_on_date')
        ->and($data['responsibility'])->toBe('household_shared')
        ->and($data['tracking_mode'])->toBe('target_amount');
});

test('preserveOwnership restores assignee and shared from the record', function () {
    $record = new Recurring([
        'family_member_id' => 4,
        'is_shared' => false,
    ]);

    $data = RecurringFormNormalizer::preserveOwnership([
        'responsibility' => 'household_shared',
        'family_member_id' => 9,
        'is_shared' => true,
    ], $record);

    expect($data['family_member_id'])->toBe(4)
        ->and($data['is_shared'])->toBeFalse()
        ->and($data['responsibility'])->toBe('family_member');
});
