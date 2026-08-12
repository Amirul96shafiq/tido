<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create([
        'household_role' => HouseholdRole::Primary,
    ]));
});

test('fixed bill requires expected amount and hides commitment fields', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Unifi',
            'type' => RecurringType::FixedBill->value,
            'cadence_preset' => 'monthly',
            'anchor_day' => 5,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['expected_amount']);
});

test('variable bill allows empty amount', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'TNB',
            'type' => RecurringType::VariableBill->value,
            'expected_amount' => null,
            'cadence_preset' => 'monthly',
            'anchor_day' => 10,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => true,
            'notify_filament' => true,
            'notify_whatsapp' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Recurring::query()->where('title', 'TNB')->value('expected_amount'))->toBeNull();
});

test('debt instalment requires instalment controls', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'PayLater',
            'type' => RecurringType::DebtInstalment->value,
            'expected_amount' => 100,
            'cadence_preset' => 'monthly',
            'anchor_day' => 1,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['instalment_total', 'instalment_remaining']);
});

test('debt instalment saves instalments and clears goal', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'PayLater Card',
            'type' => RecurringType::DebtInstalment->value,
            'expected_amount' => 150,
            'instalment_total' => 6,
            'instalment_remaining' => 6,
            'goal_target_amount' => 900,
            'cadence_preset' => 'monthly',
            'anchor_day' => 1,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => true,
            'notify_filament' => true,
            'notify_whatsapp' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'PayLater Card')->first();

    expect($recurring)->not->toBeNull()
        ->and($recurring->instalment_total)->toBe(6)
        ->and($recurring->instalment_remaining)->toBe(6)
        ->and($recurring->goal_target_amount)->toBeNull();
});

test('transfer open-ended clears commitment fields', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Tabung ASB',
            'type' => RecurringType::TransferInvestment->value,
            'tracking_mode' => 'open_ended',
            'expected_amount' => 50,
            'goal_target_amount' => 600,
            'instalment_total' => 12,
            'instalment_remaining' => 12,
            'cadence_preset' => 'monthly',
            'anchor_day' => 1,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => true,
            'notify_filament' => true,
            'notify_whatsapp' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Tabung ASB')->first();

    expect($recurring->goal_target_amount)->toBeNull()
        ->and($recurring->instalment_total)->toBeNull()
        ->and($recurring->instalment_remaining)->toBeNull();
});

test('custom cadence requires interval months', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Custom Bill',
            'type' => RecurringType::FixedBill->value,
            'expected_amount' => 20,
            'cadence_preset' => 'custom',
            'interval_months' => null,
            'anchor_day' => 1,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['interval_months']);
});

test('once cadence saves without interval months', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');

    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Road tax',
            'type' => RecurringType::FixedBill->value,
            'expected_amount' => 300,
            'cadence_preset' => 'once',
            'due_date' => '2026-09-15',
            'responsibility' => 'primary',
            'is_active' => true,
            'notify_filament' => true,
            'notify_whatsapp' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Road tax')->first();

    expect($recurring->frequency)->toBe(RecurringFrequency::Once)
        ->and($recurring->interval_months)->toBeNull()
        ->and($recurring->starts_on?->toDateString())->toBe('2026-09-15')
        ->and($recurring->ends_on)->toBeNull()
        // Generator clears next_due_on after creating the single once occurrence.
        ->and($recurring->next_due_on)->toBeNull()
        ->and($recurring->occurrences()->count())->toBe(1);
});

test('end on date requires ends_on not before starts_on', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Finite Bill',
            'type' => RecurringType::Subscription->value,
            'expected_amount' => 40,
            'cadence_preset' => 'monthly',
            'anchor_day' => 5,
            'starts_on' => '2026-08-20',
            'end_rule' => 'end_on_date',
            'ends_on' => '2026-08-01',
            'responsibility' => 'primary',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);
});

test('primary responsibility clears family member and shared', function () {
    $member = FamilyMember::factory()->create();

    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Primary Bill',
            'type' => RecurringType::Subscription->value,
            'expected_amount' => 25,
            'cadence_preset' => 'monthly',
            'anchor_day' => 8,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'family_member_id' => $member->id,
            'is_shared' => true,
            'is_active' => true,
            'notify_filament' => true,
            'notify_whatsapp' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Primary Bill')->first();

    expect($recurring->family_member_id)->toBeNull()
        ->and($recurring->is_shared)->toBeFalse();
});

test('family member responsibility requires member select', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Member Bill',
            'type' => RecurringType::Subscription->value,
            'expected_amount' => 25,
            'cadence_preset' => 'monthly',
            'anchor_day' => 8,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'family_member',
            'family_member_id' => null,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['family_member_id']);
});

test('household shared clears family member', function () {
    $member = FamilyMember::factory()->create();

    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Shared Bill',
            'type' => RecurringType::Subscription->value,
            'expected_amount' => 25,
            'cadence_preset' => 'monthly',
            'anchor_day' => 8,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'household_shared',
            'family_member_id' => $member->id,
            'is_active' => true,
            'notify_filament' => true,
            'notify_whatsapp' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Shared Bill')->first();

    expect($recurring->family_member_id)->toBeNull()
        ->and($recurring->is_shared)->toBeTrue();
});

test('inactive recurring preserves reminder preferences', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Paused Sub',
            'type' => RecurringType::Subscription->value,
            'expected_amount' => 84.79,
            'cadence_preset' => 'monthly',
            'anchor_day' => 8,
            'starts_on' => now()->toDateString(),
            'responsibility' => 'primary',
            'is_active' => false,
            'notify_filament' => true,
            'notify_whatsapp' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Paused Sub')->first();

    expect($recurring->is_active)->toBeFalse()
        ->and($recurring->notify_filament)->toBeTrue()
        ->and($recurring->notify_whatsapp)->toBeFalse();
});

test('edit preserves next due on when schedule fields change', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');

    $label = Label::factory()->create();
    $recurring = Recurring::factory()->create([
        'title' => 'Stable Due',
        'type' => RecurringType::Subscription,
        'label_id' => $label->id,
        'expected_amount' => 50,
        'anchor_day' => 5,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-12-05',
        'interval_months' => 1,
    ]);

    Livewire::test(EditRecurring::class, ['record' => $recurring->getRouteKey()])
        ->fillForm([
            'anchor_day' => 20,
            'starts_on' => '2026-08-20',
            'cadence_preset' => 'monthly',
            'responsibility' => 'primary',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($recurring->fresh()->next_due_on?->toDateString())->toBe('2026-12-05')
        ->and($recurring->fresh()->anchor_day)->toBe(20);
});

test('switching type to subscription clears stale commitment values', function () {
    $recurring = Recurring::factory()->withGoal(600, 50)->create([
        'title' => 'Was Transfer',
        'instalment_total' => 12,
        'instalment_remaining' => 10,
    ]);

    Livewire::test(EditRecurring::class, ['record' => $recurring->getRouteKey()])
        ->fillForm([
            'type' => RecurringType::Subscription->value,
            'expected_amount' => 50,
            'cadence_preset' => 'monthly',
            'responsibility' => 'primary',
            'is_active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $recurring->fresh();

    expect($fresh->type)->toBe(RecurringType::Subscription)
        ->and($fresh->goal_target_amount)->toBeNull()
        ->and($fresh->instalment_total)->toBeNull()
        ->and($fresh->instalment_remaining)->toBeNull();
});
