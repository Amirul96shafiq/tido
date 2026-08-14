<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Filament\Resources\Recurrings\Pages\ListRecurrings;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-14 21:00:00');

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));
});

test('next open due prefers earliest unpaid occurrence over generation cursor', function (): void {
    $recurring = Recurring::factory()->create([
        'title' => 'TIME Internet',
        'anchor_day' => 17,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-10-17',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-17',
        'period_start' => '2026-08-17',
        'period_end' => '2026-09-16',
        'status' => RecurringOccurrenceStatus::Completed,
        'expense_id' => null,
        'actual_amount' => 102.80,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-09-17',
        'period_start' => '2026-09-17',
        'period_end' => '2026-10-16',
        'status' => RecurringOccurrenceStatus::Upcoming,
    ]);

    expect($recurring->fresh()->nextOpenDueOn()?->toDateString())->toBe('2026-09-17')
        ->and($recurring->fresh()->next_due_on?->toDateString())->toBe('2026-10-17');
});

test('next open due falls back to generation cursor when no open occurrence exists', function (): void {
    $recurring = Recurring::factory()->create([
        'title' => 'Indah Water',
        'anchor_day' => 24,
        'next_due_on' => '2027-01-24',
    ]);

    expect($recurring->nextOpenDueOn()?->toDateString())->toBe('2027-01-24');
});

test('recurrings list shows earliest open due not october cursor', function (): void {
    $recurring = Recurring::factory()->create([
        'title' => 'TIME Internet',
        'anchor_day' => 17,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-10-17',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-17',
        'period_start' => '2026-08-17',
        'period_end' => '2026-09-16',
        'status' => RecurringOccurrenceStatus::Completed,
        'expense_id' => null,
        'actual_amount' => 102.80,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-09-17',
        'period_start' => '2026-09-17',
        'period_end' => '2026-10-16',
        'status' => RecurringOccurrenceStatus::Upcoming,
    ]);

    Livewire::test(ListRecurrings::class)
        ->assertOk()
        ->assertTableColumnStateSet('next_due_on', '2026-09-17', $recurring)
        ->assertTableColumnStateNotSet('next_due_on', '2026-10-17', $recurring)
        ->assertSee('17/09/2026')
        ->assertDontSee('17/10/2026');
});

test('recurring summary shows earliest open due on edit', function (): void {
    $recurring = Recurring::factory()->create([
        'title' => 'TIME Internet',
        'anchor_day' => 17,
        'starts_on' => '2026-08-01',
        'next_due_on' => '2026-10-17',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-17',
        'period_start' => '2026-08-17',
        'period_end' => '2026-09-16',
        'status' => RecurringOccurrenceStatus::Completed,
        'expense_id' => null,
        'actual_amount' => 102.80,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-09-17',
        'period_start' => '2026-09-17',
        'period_end' => '2026-10-16',
        'status' => RecurringOccurrenceStatus::Upcoming,
    ]);

    Livewire::test(EditRecurring::class, ['record' => $recurring->getRouteKey()])
        ->assertOk()
        ->assertSee('Next due: 17 Sep 2026')
        ->assertDontSee('Next due: 17 Oct 2026');
});
