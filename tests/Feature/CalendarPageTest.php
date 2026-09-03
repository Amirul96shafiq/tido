<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Pages\CalendarPage;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Support\Calendar\UserMenuCalendarLabel;
use Carbon\Carbon;
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
    $currentMonthLabelShort = now()->format('m/y');

    $component = Livewire::test(CalendarPage::class)
        ->assertOk()
        ->assertSee('Calendar (')
        ->assertSee($currentMonthLabel)
        ->assertSee($currentMonthLabelShort)
        ->assertSee('Today');

    expect($component->html())
        ->toContain('hidden sm:inline">'.$currentMonthLabel.'</span>')
        ->toContain('sm:hidden">'.$currentMonthLabelShort.'</span>')
        ->toContain('tido-calendar-heading-month-trigger')
        ->toContain('aria-label="Change month, '.$currentMonthLabel.'"')
        ->toContain('tido-calendar-heading-month-panel')
        ->toContain('fi-dashboard-month-filter')
        ->toContain('transition-opacity ease-out duration-200')
        ->toContain('handleClickAway($event)')
        ->toContain('.tido-calendar-event-chip, .tido-calendar__more-btn')
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
        ->toContain('tido-calendar__grid-wrap custom-scrollbar')
        ->not->toContain('Filter Events')
        ->not->toContain('Show All');
});

test('calendar page styles completed recurring occurrences with reduced opacity and strikethrough', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $recurring = Recurring::factory()->create(['title' => 'Paid PTPTN']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Completed,
        'due_on' => now()->toDateString(),
        'expected_amount' => 250.00,
    ]);

    $this->actingAs($user);

    expect(Livewire::test(CalendarPage::class)->html())
        ->toContain('Paid PTPTN')
        ->toContain('tido-calendar-event-chip--completed');
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

    expect(Livewire::test(CalendarPage::class)->html())
        ->toContain('TIME Internet')
        ->toContain("mountAction('confirmSkipOccurrence'")
        ->toContain('Skip');
});

test('calendar page skip occurrence requires confirmation and marks occurrence skipped', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $recurring = Recurring::factory()->once()->create(['title' => 'TIME Internet']);
    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 89.90,
    ]);

    $this->actingAs($user);

    Livewire::test(CalendarPage::class)
        ->mountAction('confirmSkipOccurrence', ['occurrenceId' => $occurrence->id])
        ->assertActionMounted('confirmSkipOccurrence')
        ->assertMountedActionModalSee('Skip occurrence?')
        ->assertMountedActionModalSee('This occurrence will be marked as skipped.')
        ->callMountedAction()
        ->assertSuccessful()
        ->assertDispatched('recurring-occurrences-updated')
        ->assertDispatched('calendar-close-popover');

    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Skipped);
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
        ->assertSet('viewMonth', $nextMonth->format('Y-m'))
        ->assertSee($nextMonthLabel);
});

test('calendar view month picker updates the viewed month', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $this->actingAs($user);

    $target = now()->addMonths(3);
    $targetKey = $target->format('Y-m');
    $targetLabel = $target->format('F Y');

    Livewire::test(CalendarPage::class)
        ->set('viewMonth', $targetKey)
        ->assertSet('year', (int) $target->year)
        ->assertSet('month', (int) $target->month)
        ->assertSet('viewMonth', $targetKey)
        ->assertSee($targetLabel);
});

test('calendar go to month clamps to minimum year and syncs view month', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $this->actingAs($user);

    $minYear = now()->year - 5;
    $expectedKey = sprintf('%04d-01', $minYear);
    $expectedLabel = Carbon::create($minYear, 1, 1)->format('F Y');

    Livewire::test(CalendarPage::class)
        ->call('goToMonth', 1, $minYear - 1)
        ->assertSet('year', $minYear)
        ->assertSet('month', 1)
        ->assertSet('viewMonth', $expectedKey)
        ->assertSee($expectedLabel);
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
        ->and($items['calendar']->getLabel())->toEqual(UserMenuCalendarLabel::html())
        ->and($items['calendar']->getUrl())->toBe(CalendarPage::getUrl());
});
