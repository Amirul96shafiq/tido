<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\DueRecurrings;
use App\Filament\Widgets\RecurringMonthSnapshot;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

test('snapshot widget shows paid progress remaining and status chips', function () {
    Expense::unsetEventDispatcher();

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $due = Recurring::factory()->create(['title' => 'TIME Internet']);
    $overdue = Recurring::factory()->create(['title' => 'Home Financing-i']);
    $upcoming = Recurring::factory()->create(['title' => 'Celcom Mobile']);
    $paid = Recurring::factory()->create(['title' => 'Netflix']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $due->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 100.00,
    ]);
    RecurringOccurrence::factory()->overdue()->create([
        'recurring_id' => $overdue->id,
        'expected_amount' => 50.50,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $upcoming->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => now()->copy()->endOfMonth()->toDateString(),
        'expected_amount' => 25.00,
    ]);

    $expense = Expense::factory()->create(['total_amount' => 55.00]);
    RecurringOccurrence::factory()->completed($expense)->create([
        'recurring_id' => $paid->id,
        'due_on' => now()->toDateString(),
        'expected_amount' => 55.00,
        'actual_amount' => 55.00,
    ]);

    Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee(RecurringMonthSnapshot::headingLabel())
        ->assertSee('RM 55.00')
        ->assertSee('Paid of RM 230.50')
        ->assertSee('RM 175.50 remaining')
        ->assertSee('Paid 1')
        ->assertSee('Overdue 1')
        ->assertSee('Due 1')
        ->assertSee('Upcoming 1')
        ->assertSee('of 4')
        ->assertSee('bills paid', false)
        ->assertSee('bg-red-100', false)
        ->assertSee('bg-amber-100', false)
        ->assertSee('bg-primary-100', false)
        ->assertDontSee('Manage');
});

test('snapshot widget shows the next open due title date and countdown', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $overdueOn = now()->subDays(3);
    $overdue = Recurring::factory()->create(['title' => 'TIME Internet']);
    $due = Recurring::factory()->create(['title' => 'Celcom Mobile']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $overdue->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => $overdueOn->toDateString(),
        'expected_amount' => 89.90,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $due->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 40.00,
    ]);

    Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee('Overdue:')
        ->assertSee('TIME Internet · '.$overdueOn->format('d M').' · 3 days overdue')
        ->assertDontSee('Upcoming Next Due:')
        ->assertDontSee('Celcom Mobile ·');
});

test('snapshot widget shows days left until the next upcoming bill', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 10:00:00', 'Asia/Kuala_Lumpur'));

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $upcoming = Recurring::factory()->create(['title' => 'TIME Internet']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $upcoming->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => '2026-08-17',
        'expected_amount' => 89.90,
    ]);

    Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee('Upcoming Next Due:')
        ->assertSee('TIME Internet · 17 Aug · 4 days left');
});

test('snapshot widget shows due today on the next bill countdown', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 10:00:00', 'Asia/Kuala_Lumpur'));

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $due = Recurring::factory()->create(['title' => 'Celcom Mobile']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $due->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => '2026-08-13',
        'expected_amount' => 40.00,
    ]);

    Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee('Upcoming Next Due:')
        ->assertSee('Celcom Mobile · 13 Aug · Due today');
});

test('snapshot widget shows empty state when no dashboard-month occurrences exist', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee('No recurring payments this month')
        ->assertDontSee('Overdue 0')
        ->assertDontSee('Paid 0')
        ->assertDontSee('RM 0.00');
});

test('family member snapshot hides primary-only recurrings', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $this->actingAs($member->loginUser);

    $own = Recurring::factory()->forFamilyMember($member)->create(['title' => 'Along Loan']);
    $shared = Recurring::factory()->shared()->create(['title' => 'Shared Bill']);
    $other = Recurring::factory()->create(['title' => 'Primary Only']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $own->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 50.00,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $shared->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 75.00,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $other->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 200.00,
    ]);

    Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee('RM 0.00')
        ->assertSee('Paid of RM 125.00')
        ->assertSee('RM 125.00 remaining')
        ->assertSee('Upcoming Next Due:')
        ->assertSee('Along Loan ·')
        ->assertDontSee('Primary Only')
        ->assertDontSee('RM 200.00');
});

test('snapshot widget sorts beside dues and spans four xl columns', function () {
    expect(RecurringMonthSnapshot::getSort())->toBe(4)
        ->and(RecurringMonthSnapshot::headingLabel())->toBe('Bills · '.now()->format('F Y'));

    expect((new ReflectionClass(RecurringMonthSnapshot::class))->getDefaultProperties()['columnSpan'])
        ->toBe([
            'default' => 'full',
            'xl' => 4,
        ]);
});

test('snapshot widget uses standard chart height', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create();

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    $height = DashboardWidgetHeights::STANDARD_CHART;

    Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee('min-height: '.$height, false)
        ->assertSee('max-height: '.$height, false)
        ->assertSee('h-full', false);
});

test('snapshot widget refreshes paid remaining and next due after skip', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 10:00:00', 'Asia/Kuala_Lumpur'));

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $upcoming = Recurring::factory()->create(['title' => 'TIME Internet']);
    $later = Recurring::factory()->create(['title' => 'Celcom Mobile']);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $upcoming->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => '2026-08-17',
        'expected_amount' => 199.14,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $later->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => '2026-08-20',
        'expected_amount' => 40.00,
    ]);

    $snapshot = Livewire::test(RecurringMonthSnapshot::class)
        ->assertOk()
        ->assertSee('Upcoming 2')
        ->assertSee('Paid of RM 239.14')
        ->assertSee('RM 239.14 remaining')
        ->assertSee('TIME Internet · 17 Aug · 4 days left')
        ->assertDontSee('Celcom Mobile ·');

    Livewire::test(DueRecurrings::class)
        ->call('skipOccurrence', $occurrence->id)
        ->assertDispatched('recurring-occurrences-updated');

    $snapshot
        ->dispatch('recurring-occurrences-updated')
        ->assertSee('Upcoming 1')
        ->assertSee('Paid of RM 40.00')
        ->assertSee('RM 40.00 remaining')
        ->assertSee('Celcom Mobile · 20 Aug · 7 days left')
        ->assertDontSee('TIME Internet ·')
        ->assertDontSee('RM 239.14');
});

test('due countdown label covers today remaining and overdue days', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 10:00:00', 'Asia/Kuala_Lumpur'));

    expect(RecurringMonthSnapshot::dueCountdownLabel(Carbon::parse('2026-08-13', 'Asia/Kuala_Lumpur')))->toBe('Due today')
        ->and(RecurringMonthSnapshot::dueCountdownLabel(Carbon::parse('2026-08-14', 'Asia/Kuala_Lumpur')))->toBe('1 day left')
        ->and(RecurringMonthSnapshot::dueCountdownLabel(Carbon::parse('2026-08-17', 'Asia/Kuala_Lumpur')))->toBe('4 days left')
        ->and(RecurringMonthSnapshot::dueCountdownLabel(Carbon::parse('2026-08-12', 'Asia/Kuala_Lumpur')))->toBe('1 day overdue')
        ->and(RecurringMonthSnapshot::dueCountdownLabel(Carbon::parse('2026-08-10', 'Asia/Kuala_Lumpur')))->toBe('3 days overdue')
        ->and(RecurringMonthSnapshot::dueCountdownLabel(null))->toBeNull();
});
