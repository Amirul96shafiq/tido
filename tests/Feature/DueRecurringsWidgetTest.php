<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Widgets\CurrentCurrency;
use App\Filament\Widgets\DueRecurrings;
use App\Filament\Widgets\MonthlyTrend;
use App\Filament\Widgets\SpendingByLabel;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('due widget shows open occurrences for primary', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create(['title' => 'TIME Internet']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('TIME Internet')
        ->assertSee(RecurringOccurrenceStatus::Due->label());
});

test('family member sees own and shared due items only', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $this->actingAs($member->loginUser);

    $own = Recurring::factory()->forFamilyMember($member)->create(['title' => 'Along Loan']);
    $shared = Recurring::factory()->shared()->create(['title' => 'Shared Bill']);
    $other = Recurring::factory()->create(['title' => 'Primary Only']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $own->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $shared->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $other->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertSee('Along Loan')
        ->assertSee('Shared Bill')
        ->assertDontSee('Primary Only');
});

test('due recurrings widget sorts after overview currency and before analytics charts', function () {
    expect(DueRecurrings::getSort())->toBe(3)
        ->and(CurrentCurrency::getSort())->toBe(2)
        ->and(MonthlyTrend::getSort())->toBe(4)
        ->and(SpendingByLabel::getSort())->toBe(5);
});
