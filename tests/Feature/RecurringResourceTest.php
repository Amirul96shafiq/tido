<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Filament\Resources\Recurrings\Pages\ListRecurrings;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('primary can open recurring index', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $this->get(RecurringResource::getUrl('index'))
        ->assertOk();
});

test('family member can open recurring index but not create', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();

    $this->actingAs($member->loginUser);

    $this->get(RecurringResource::getUrl('index'))
        ->assertOk();

    $this->get(RecurringResource::getUrl('create'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('primary can create recurring from form', function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $label = Label::factory()->create();

    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Cursor Pro',
            'type' => RecurringType::Subscription->value,
            'label_id' => $label->id,
            'expected_amount' => 84.79,
            'cadence_preset' => 'monthly',
            'frequency' => RecurringFrequency::Repeating->value,
            'interval_months' => 1,
            'anchor_day' => 8,
            'starts_on' => now()->toDateString(),
            'end_rule' => 'ongoing',
            'responsibility' => 'primary',
            'merchant_aliases' => ['Cursor', 'Anysphere'],
            'notify_filament' => true,
            'notify_whatsapp' => true,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Cursor Pro')->first();

    expect($recurring)->not->toBeNull()
        ->and($recurring->frequency)->toBe(RecurringFrequency::Repeating)
        ->and($recurring->interval_months)->toBe(1)
        ->and($recurring->family_member_id)->toBeNull()
        ->and($recurring->is_shared)->toBeFalse();
});

test('list page renders for primary', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $label = Label::factory()->create(['name' => 'Subscriptions']);
    $recurring = Recurring::factory()->create([
        'title' => 'GPROP',
        'label_id' => $label->id,
        'expected_amount' => 84.79,
    ]);

    $component = Livewire::test(ListRecurrings::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Recurring::all())
        ->assertTableColumnExists('type', function (TextColumn $column): bool {
            return $column->isBadge();
        })
        ->assertTableColumnExists('label.name', function (TextColumn $column): bool {
            return $column->isBadge()
                && $column->getPlaceholder() === 'None';
        })
        ->assertTableColumnStateSet('label.name', 'Subscriptions', $recurring)
        ->assertTableColumnExists('cadence', function (TextColumn $column): bool {
            return $column->isBadge();
        })
        ->assertTableColumnStateSet('cadence', 'Monthly', $recurring)
        ->assertTableColumnExists('editedBy.name', function (TextColumn $column): bool {
            return $column->getPlaceholder() === 'System';
        });

    expect($component->html())->toContain('fi-count-up');
});

test('list page shows primary username when assigned to primary', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
        'name' => 'Primary Account Owner',
        'display_name' => 'admin',
    ]));

    $member = FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'nor',
    ]);

    $primaryRecurring = Recurring::factory()->create([
        'title' => 'Primary Recurring',
        'family_member_id' => null,
    ]);

    $familyRecurring = Recurring::factory()->forFamilyMember($member)->create([
        'title' => 'Family Recurring',
    ]);

    Livewire::test(ListRecurrings::class)
        ->assertOk()
        ->assertSee('Assigned to')
        ->assertTableColumnStateSet('assigned_to', 'admin', $primaryRecurring)
        ->assertTableColumnStateSet('assigned_to', 'nor', $familyRecurring)
        ->assertTableColumnFormattedStateSet('editedBy.name', 'admin', $primaryRecurring)
        ->assertDontSee('Nor Ezrieana Harun');
});

test('primary can duplicate a recurring from the list', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $source = Recurring::factory()->withGoal(1200, 100)->create([
        'title' => 'Tabung Haji',
        'starts_on' => now()->subMonths(6)->toDateString(),
        'next_due_on' => now()->subMonths(2)->toDateString(),
        'prior_contributed_amount' => 300,
        'merchant_aliases' => ['Tabung', 'TH'],
    ]);

    $sourceOccurrence = RecurringOccurrence::factory()->completed()->create([
        'recurring_id' => $source->id,
        'period_start' => now()->subMonths(3)->toDateString(),
        'period_end' => now()->subMonths(2)->subDay()->toDateString(),
        'due_on' => now()->subMonths(3)->toDateString(),
        'expected_amount' => 100,
        'actual_amount' => 100,
    ]);

    $sourceOccurrenceCount = $source->occurrences()->count();

    $page = Livewire::test(ListRecurrings::class)
        ->callAction(TestAction::make('replicate')->table($source))
        ->assertNotified('Recurring duplicated');

    $replica = Recurring::query()
        ->where('title', 'Tabung Haji')
        ->whereKeyNot($source->id)
        ->first();

    expect($replica)->not->toBeNull();

    $page->assertRedirect(RecurringResource::getUrl('edit', ['record' => $replica]));

    expect($source->fresh()->occurrences()->count())->toBe($sourceOccurrenceCount)
        ->and(RecurringOccurrence::query()->whereKey($sourceOccurrence->id)->exists())->toBeTrue()
        ->and($replica->starts_on?->toDateString())->toBe(now()->toDateString())
        ->and($replica->prior_contributed_amount)->toBeNull()
        ->and($replica->instalment_remaining)->toBe($replica->instalment_total)
        ->and($replica->merchant_aliases)->toBe(['Tabung', 'TH'])
        ->and($replica->occurrences()->count())->toBeGreaterThan(0)
        ->and($replica->sort_order)->not->toBe($source->sort_order);
});

test('primary can bulk duplicate recurrings from the list', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $first = Recurring::factory()->create(['title' => 'Netflix']);
    $second = Recurring::factory()->create(['title' => 'Spotify']);

    expect(Recurring::query()->count())->toBe(2);

    Livewire::test(ListRecurrings::class)
        ->selectTableRecords([$first->getKey(), $second->getKey()])
        ->callAction(TestAction::make('duplicate')->table()->bulk())
        ->assertNotified('2 recurrings duplicated')
        ->assertNoRedirect();

    expect(Recurring::query()->count())->toBe(4)
        ->and(Recurring::query()->where('title', 'Netflix')->count())->toBe(2)
        ->and(Recurring::query()->where('title', 'Spotify')->count())->toBe(2);
});

test('recurrings table supports deleted records filter and soft delete actions', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $active = Recurring::factory()->create(['title' => 'Active Recurring']);
    $trashed = Recurring::factory()->create(['title' => 'Trashed Recurring']);
    $trashed->delete();

    Livewire::test(ListRecurrings::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$trashed])
        ->assertTableFilterExists('trashed', fn ($filter): bool => $filter instanceof TrashedFilter)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$active, $trashed])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$active]);

    Livewire::test(EditRecurring::class, ['record' => $trashed->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('restore')
        ->assertActionExists('forceDelete');
});
