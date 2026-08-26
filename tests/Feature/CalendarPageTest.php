<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Pages\CalendarPage;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('calendar page renders for primary user', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $this->actingAs($user);

    $currentMonthLabel = now()->format('F Y');

    $component = Livewire::test(CalendarPage::class)
        ->assertOk()
        ->assertSee('Calendar ('.$currentMonthLabel.')')
        ->assertSee('Today');

    expect($component->html())
        ->toContain('fi-ta-filters-dropdown')
        ->toContain('fi-ta-filters-body')
        ->toContain('fi-ta-filters-actions-ctn')
        ->toContain('fi-fixed-positioning-context')
        ->toContain('fi-calendar-filters-trigger')
        ->toContain('resetTypeFilter')
        ->toContain('aria-label="Filter"')
        ->toContain('aria-label="Reset"')
        ->toContain('Recurring Dues')
        ->toContain('Birthdays')
        ->toContain('max-height: min(40vh, 20rem)')
        ->not->toContain('Filter Events')
        ->not->toContain('Show All');
});

test('calendar page shows recurring due on the due date', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $recurring = Recurring::factory()->create(['title' => 'TIME Internet']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 89.90,
    ]);

    $this->actingAs($user);

    Livewire::test(CalendarPage::class)
        ->assertSee('TIME Internet');
});

test('calendar page shows household birthday in the viewed month', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
        'date_of_birth' => now()->format('Y-m-d'),
        'display_name' => 'Calendar Primary',
    ]);

    $this->actingAs($user);

    Livewire::test(CalendarPage::class)
        ->assertSee('Calendar Primary');
});

test('calendar month navigation updates the viewed month', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $this->actingAs($user);

    $nextMonth = now()->addMonth();
    $nextMonthLabel = $nextMonth->format('F Y');

    Livewire::test(CalendarPage::class)
        ->call('nextMonth')
        ->assertSet('year', (int) $nextMonth->year)
        ->assertSet('month', (int) $nextMonth->month)
        ->assertSee('Calendar ('.$nextMonthLabel.')');
});

test('calendar type filter can hide birthdays', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
        'date_of_birth' => now()->format('Y-m-d'),
        'display_name' => 'Only Birthday',
    ]);

    $this->actingAs($user);

    Livewire::test(CalendarPage::class)
        ->set('typeFilter.birthdays', false)
        ->assertDontSee('Only Birthday');
});

test('calendar type filter reset restores all event types', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
        'date_of_birth' => now()->format('Y-m-d'),
        'display_name' => 'Only Birthday',
    ]);

    $this->actingAs($user);

    Livewire::test(CalendarPage::class)
        ->set('typeFilter.birthdays', false)
        ->assertDontSee('Only Birthday')
        ->call('resetTypeFilter')
        ->assertSee('Only Birthday')
        ->assertSet('typeFilter.birthdays', true)
        ->assertSet('typeFilter.recurring_dues', true);
});

test('family member can access calendar page', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $user = $member->loginUser;

    $this->actingAs($user);

    $this->get(CalendarPage::getUrl())
        ->assertSuccessful();
});

test('user menu includes calendar link before changelogs', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $items = Filament::getUserMenuItems();

    expect(array_keys($items))->toBe(['profile', 'calendar', 'changelogs', 'notifications', 'logout'])
        ->and($items['calendar']->getLabel())->toBe('Calendar')
        ->and($items['calendar']->getUrl())->toBe(CalendarPage::getUrl());
});
