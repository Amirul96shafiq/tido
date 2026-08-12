<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringOccurrenceStatus;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\CurrentCurrency;
use App\Filament\Widgets\DueRecurrings;
use App\Filament\Widgets\MonthlyTrend;
use App\Filament\Widgets\SpendingByLabel;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        'expected_amount' => 89.90,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('1 Recurring Payment Due')
        ->assertSee('RM 89.90')
        ->assertSee('Manage')
        ->assertSee('TIME Internet')
        ->assertSee(RecurringOccurrenceStatus::Due->label());
});

test('due widget header total sums expected amounts', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $first = Recurring::factory()->create();
    $second = Recurring::factory()->create();

    RecurringOccurrence::factory()->create([
        'recurring_id' => $first->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 100.00,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $second->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => now()->subDay()->toDateString(),
        'expected_amount' => 50.50,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('2 Recurring Payment Dues')
        ->assertSee('RM 150.50')
        ->assertSee('/ RM 150.50');
});

test('due widget shows completed occurrences at reduced opacity with completed status', function () {
    Expense::unsetEventDispatcher();

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $open = Recurring::factory()->create(['title' => 'TIME Internet']);
    $paid = Recurring::factory()->create(['title' => 'Netflix']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $open->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 102.80,
    ]);

    $completedAt = now()->subHours(2);
    $expense = Expense::factory()->create([
        'date_time' => $completedAt,
        'total_amount' => 55.00,
    ]);

    RecurringOccurrence::factory()->completed($expense)->create([
        'recurring_id' => $paid->id,
        'due_on' => now()->toDateString(),
        'expected_amount' => 55.00,
        'actual_amount' => 55.00,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('1 Recurring Payment Due')
        ->assertSee('RM 102.80')
        ->assertSee('/ RM 157.80')
        ->assertSee('TIME Internet')
        ->assertSee('Netflix')
        ->assertSee('Completed · '.$completedAt->format('d M Y H:i'))
        ->assertSee('opacity-50', false)
        ->assertSee('Skip')
        ->assertSee('disabled', false)
        ->assertSeeInOrder([
            'TIME Internet',
            'Netflix',
        ]);
});

test('due widget hides completed occurrences from previous months', function () {
    Expense::unsetEventDispatcher();

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create(['title' => 'Old Paid Bill']);

    $occurrence = RecurringOccurrence::factory()->completed()->create([
        'recurring_id' => $recurring->id,
        'due_on' => now()->subMonth()->toDateString(),
        'expected_amount' => 40.00,
        'actual_amount' => 40.00,
    ]);

    $occurrence->forceFill([
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subMonth(),
    ])->saveQuietly();

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('0 Recurring Payment Dues')
        ->assertDontSee('Old Paid Bill');
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
        ->assertDontSee('Primary Only')
        ->assertDontSee('Manage')
        ->assertDontSee('wire:sort="reorderRecurrings"', false);
});

test('family member sees current-month upcoming occurrences', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $this->actingAs($member->loginUser);

    $own = Recurring::factory()->forFamilyMember($member)->create(['title' => 'Celcom Mobile']);
    $dueOn = now()->copy()->addDays(12)->startOfDay();

    if ($dueOn->month !== now()->month) {
        $dueOn = now()->copy()->endOfMonth()->startOfDay();
    }

    RecurringOccurrence::factory()->create([
        'recurring_id' => $own->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => $dueOn->toDateString(),
        'expected_amount' => 338.55,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('1 Recurring Payment Due')
        ->assertSee('Celcom Mobile')
        ->assertSee('Upcoming · '.$dueOn->format('d M Y'))
        ->assertSee('RM 338.55');
});

test('due widget hides upcoming occurrences from future months', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create(['title' => 'Next Month Bill']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => now()->copy()->addMonthNoOverflow()->startOfMonth()->addDays(10)->toDateString(),
        'expected_amount' => 99.00,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('0 Recurring Payment Dues')
        ->assertDontSee('Next Month Bill');
});

test('due recurrings widget sorts after overview currency and before analytics charts', function () {
    expect(DueRecurrings::getSort())->toBe(3)
        ->and(CurrentCurrency::getSort())->toBe(2)
        ->and(MonthlyTrend::getSort())->toBe(4)
        ->and(SpendingByLabel::getSort())->toBe(5);
});

test('due recurrings list height matches monthly spending trend chart', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create();

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    $height = DashboardWidgetHeights::TREND_CHART;

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('max-height: '.$height, false)
        ->assertSee('min-height: '.$height, false)
        ->assertSee('custom-scrollbar grid flex-1 grid-cols-1 gap-1', false)
        ->assertSee('sm:grid-cols-2', false)
        ->assertSee('pr-2', false)
        ->assertSee('-mx-1 flex min-w-0 items-center gap-2 rounded-xl px-2 py-3', false);

    expect((new ReflectionClass(MonthlyTrend::class))->getDefaultProperties()['maxHeight'])
        ->toBe($height);
});

test('due recurrings widget uses payment-card two-zone layout', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->shared()->create([
        'title' => 'Netflix Family',
        'sort_order' => 0,
    ]);

    $dueOn = now();

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => $dueOn->toDateString(),
        'expected_amount' => 55.00,
    ]);

    $editUrl = RecurringResource::getUrl('edit', ['record' => $recurring]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSeeInOrder([
            'Netflix Family',
            'Monthly',
            'Shared',
            'Due '.$dueOn->format('d M Y'),
            'Subscription',
            'RM 55.00',
            'Skip',
        ])
        ->assertSee($editUrl, false)
        ->assertSee('wire:navigate', false)
        ->assertSee('wire:sort="reorderRecurrings"', false)
        ->assertSee('wire:sort:handle', false)
        ->assertSee('x-ref="marqueeText"', false)
        ->assertSee('tido-text-marquee-clip', false)
        ->assertSee('flex min-w-0 flex-1 flex-col gap-1', false)
        ->assertSee('flex min-w-0 flex-1 items-center justify-between gap-2', false)
        ->assertSee('sm:gap-4', false)
        ->assertSee('fi-avatar', false)
        ->assertDontSee('color-mix(in srgb', false)
        ->assertDontSee('bg-warning-100 text-warning-700 dark:bg-warning-400/25 dark:text-warning-300', false)
        ->assertDontSee('bg-danger-100 text-danger-700 dark:bg-danger-400/25 dark:text-danger-300', false);
});

test('due recurrings widget shows primary owner avatar between drag handle and content', function () {
    Storage::fake('public');

    $primary = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
        'name' => 'Primary Account',
        'display_name' => null,
        'avatar_url' => 'avatars/primary-owner.png',
    ]);

    Storage::disk('public')->put('avatars/primary-owner.png', 'avatar');

    $this->actingAs($primary);

    $recurring = Recurring::factory()->create([
        'title' => 'TIME Internet',
        'family_member_id' => null,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 89.90,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSeeHtml('src="'.e($primary->getFilamentAvatarUrl()).'"')
        ->assertSeeHtml('alt="Primary Account"')
        ->assertSee("content: 'Primary Account',", false)
        ->assertSee('x-tooltip="{', false)
        ->assertSeeInOrder([
            'wire:sort:handle',
            'fi-avatar',
            'TIME Internet',
        ], false);
});

test('due recurrings widget shows assigned family member owner avatar', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $member = FamilyMember::factory()->create([
        'name' => 'Sample Spouse',
        'display_name' => null,
        'avatar_url' => 'avatars/spouse-owner.png',
    ]);

    Storage::disk('public')->put('avatars/spouse-owner.png', 'avatar');

    $recurring = Recurring::factory()->forFamilyMember($member)->create([
        'title' => 'Spouse Gym',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 120.00,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSeeHtml('src="'.e($member->getFilamentAvatarUrl()).'"')
        ->assertSeeHtml('alt="Sample Spouse"')
        ->assertSee("content: 'Sample Spouse',", false)
        ->assertSee('Spouse Gym');
});

test('due recurrings widget shows overdue status on secondary meta line', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create([
        'title' => 'GPROP Monthly Bills',
        'type' => RecurringType::VariableBill,
    ]);
    $dueOn = now()->subDays(5);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => $dueOn->toDateString(),
        'expected_amount' => 199.14,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSeeInOrder([
            'GPROP Monthly Bills',
            'Monthly',
            'Overdue · '.$dueOn->format('d M Y'),
            'Variable bill',
            'RM 199.14',
            'Skip',
        ])
        ->assertDontSee('Overdue · due ', false)
        ->assertDontSee('bg-danger-100 text-danger-700 dark:bg-danger-400/25 dark:text-danger-300', false);
});

test('due recurrings title flash is red when open dues exist', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create(['title' => 'TIME Internet']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'expected_amount' => 89.90,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('1 Recurring Payment Due')
        ->assertSee('animate-ping', false)
        ->assertSee('bg-red-500', false)
        ->assertDontSee('bg-gray-400 dark:bg-gray-500', false);
});

test('due recurrings title flash is gray when no open dues', function () {
    Expense::unsetEventDispatcher();

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create(['title' => 'Paid Netflix']);
    $expense = Expense::factory()->create([
        'date_time' => now()->subHour(),
        'total_amount' => 55.00,
    ]);

    RecurringOccurrence::factory()->completed($expense)->create([
        'recurring_id' => $recurring->id,
        'due_on' => now()->toDateString(),
        'expected_amount' => 55.00,
        'actual_amount' => 55.00,
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('0 Recurring Payment Dues')
        ->assertSee('Paid Netflix')
        ->assertSee('animate-ping', false)
        ->assertSee('bg-gray-400 dark:bg-gray-500', false)
        ->assertDontSee('bg-red-500', false);
});

test('due recurrings widget renders items in recurring sort order', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $first = Recurring::factory()->create(['title' => 'First Due']);
    $second = Recurring::factory()->create(['title' => 'Second Due']);
    $first->update(['sort_order' => 10]);
    $second->update(['sort_order' => 20]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $first->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $second->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => now()->subDay()->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSeeInOrder([
            'First Due',
            'Second Due',
        ]);

    $first->update(['sort_order' => 20]);
    $second->update(['sort_order' => 10]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSeeInOrder([
            'Second Due',
            'First Due',
        ]);
});

test('due recurrings widget persists drag and drop reorder', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $first = Recurring::factory()->create(['title' => 'Alpha Due']);
    $second = Recurring::factory()->create(['title' => 'Beta Due']);
    $first->update(['sort_order' => 10]);
    $second->update(['sort_order' => 20]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $first->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $second->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->call('reorderRecurrings', $second->id, 0)
        ->assertSuccessful();

    expect($first->fresh()->sort_order)->toBe(20)
        ->and($second->fresh()->sort_order)->toBe(10);

    Livewire::test(DueRecurrings::class)
        ->assertSeeInOrder([
            'Beta Due',
            'Alpha Due',
        ]);
});

test('due recurrings widget blocks reorder for family members', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $this->actingAs($member->loginUser);

    $first = Recurring::factory()->forFamilyMember($member)->create(['title' => 'Alpha Cap']);
    $second = Recurring::factory()->forFamilyMember($member)->create(['title' => 'Beta Cap']);
    $first->update(['sort_order' => 10]);
    $second->update(['sort_order' => 20]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $first->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $second->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->call('reorderRecurrings', $second->id, 0)
        ->assertSuccessful();

    expect($first->fresh()->sort_order)->toBe(10)
        ->and($second->fresh()->sort_order)->toBe(20);
});

test('due widget shows skipped occurrences at reduced opacity with revert action', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $dueOn = now()->startOfDay();

    $open = Recurring::factory()->create(['title' => 'TIME Internet']);
    $skipped = Recurring::factory()->create(['title' => 'Netflix']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $open->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => $dueOn->toDateString(),
    ]);

    RecurringOccurrence::factory()->skipped()->create([
        'recurring_id' => $skipped->id,
        'due_on' => $dueOn->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->assertOk()
        ->assertSee('TIME Internet')
        ->assertSee('Netflix')
        ->assertSee('Skipped · '.$dueOn->format('d M Y'))
        ->assertSee('opacity-50', false)
        ->assertSee('Skipped')
        ->assertSee('Revert Back')
        ->assertSee('disabled', false)
        ->assertSee("mountAction('confirmRevertOccurrence'", false)
        ->assertDontSee('wire:confirm', false);
});

test('due widget skip requires filament confirmation modal', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create(['title' => 'TIME Internet']);
    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->mountAction('confirmSkipOccurrence', ['occurrenceId' => $occurrence->id])
        ->assertActionMounted('confirmSkipOccurrence')
        ->assertMountedActionModalSee('Skip occurrence?')
        ->assertMountedActionModalSee('This occurrence will be marked as skipped.')
        ->callMountedAction()
        ->assertSuccessful();

    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Skipped);
});

test('due widget revert requires filament confirmation modal', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');

    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $recurring = Recurring::factory()->create(['title' => 'Netflix']);
    $occurrence = RecurringOccurrence::factory()->skipped()->create([
        'recurring_id' => $recurring->id,
        'due_on' => now()->toDateString(),
    ]);

    Livewire::test(DueRecurrings::class)
        ->mountAction('confirmRevertOccurrence', ['occurrenceId' => $occurrence->id])
        ->assertActionMounted('confirmRevertOccurrence')
        ->assertMountedActionModalSee('Revert skipped occurrence?')
        ->assertMountedActionModalSee('This occurrence will return to the due list.')
        ->callMountedAction()
        ->assertSuccessful();

    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Due);
});
