<?php

declare(strict_types=1);

use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Filament\Resources\Recurrings\Pages\ListRecurrings;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\User;
use App\Support\HouseholdAccess;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{member: FamilyMember, other: FamilyMember, user: User, own: Recurring, primary: Recurring, otherOwned: Recurring, shared: Recurring}
 */
function recurringOwnershipFixtures(): array
{
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60111111111',
    ]);
    $other = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60122222222',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $own = Recurring::factory()->forFamilyMember($member)->create([
        'title' => 'Own Recurring',
    ]);
    $primary = Recurring::factory()->create([
        'title' => 'Primary Recurring',
        'family_member_id' => null,
    ]);
    $otherOwned = Recurring::factory()->forFamilyMember($other)->create([
        'title' => 'Other Recurring',
    ]);
    $shared = Recurring::factory()->shared()->create([
        'title' => 'Shared Recurring',
        'family_member_id' => null,
    ]);

    return [
        'member' => $member,
        'other' => $other,
        'user' => $user,
        'own' => $own,
        'primary' => $primary,
        'otherOwned' => $otherOwned,
        'shared' => $shared,
    ];
}

test('household access allows family member to mutate only assigned recurrings', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(HouseholdAccess::canMutateRecurring($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateRecurring($fixtures['primary']))->toBeFalse()
        ->and(HouseholdAccess::canMutateRecurring($fixtures['otherOwned']))->toBeFalse()
        ->and(HouseholdAccess::canMutateRecurring($fixtures['shared']))->toBeFalse();
});

test('primary user can mutate any recurring', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs(User::factory()->create());

    expect(HouseholdAccess::canMutateRecurring($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateRecurring($fixtures['primary']))->toBeTrue()
        ->and(HouseholdAccess::canMutateRecurring($fixtures['otherOwned']))->toBeTrue()
        ->and(HouseholdAccess::canMutateRecurring($fixtures['shared']))->toBeTrue();
});

test('family member can list every household recurring', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $this->get(RecurringResource::getUrl('index'))
        ->assertOk();

    Livewire::test(ListRecurrings::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([
            $fixtures['own'],
            $fixtures['primary'],
            $fixtures['otherOwned'],
            $fixtures['shared'],
        ]);
});

test('family member can open edit page for assigned recurring only', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(EditRecurring::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditRecurring::class, ['record' => $fixtures['primary']->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(EditRecurring::class, ['record' => $fixtures['otherOwned']->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(EditRecurring::class, ['record' => $fixtures['shared']->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('family member cannot open the create recurring page', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $this->get(RecurringResource::getUrl('create'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(CreateRecurring::class)
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    expect(RecurringResource::canCreate())->toBeFalse();
});

test('family member sees create recurring action visible and disabled', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListRecurrings::class)
        ->assertSuccessful()
        ->assertActionVisible('create')
        ->assertActionDisabled('create');
});

test('family member sees duplicate actions visible and disabled', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListRecurrings::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('replicate')->table($fixtures['own']))
        ->assertActionDisabled(TestAction::make('replicate')->table($fixtures['own']))
        ->assertActionVisible(TestAction::make('duplicate')->table()->bulk())
        ->assertActionDisabled(TestAction::make('duplicate')->table()->bulk());

    Livewire::test(EditRecurring::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('replicate')
        ->assertActionDisabled('replicate');

    expect(Recurring::query()->count())->toBe(4);

    Livewire::test(ListRecurrings::class)
        ->callAction(TestAction::make('replicate')->table($fixtures['own']));

    expect(Recurring::query()->count())->toBe(4);
});

test('family member cannot select non-assigned recurrings for bulk actions', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $table = Livewire::test(ListRecurrings::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    expect($table->isRecordSelectable($fixtures['own']))->toBeTrue()
        ->and($table->isRecordSelectable($fixtures['primary']))->toBeFalse()
        ->and($table->isRecordSelectable($fixtures['otherOwned']))->toBeFalse()
        ->and($table->isRecordSelectable($fixtures['shared']))->toBeFalse()
        ->and($table->getRecordClasses($fixtures['own']))->not->toContain('tido-ta-record-unsupported')
        ->and($table->getRecordClasses($fixtures['primary']))->toContain('tido-ta-record-unsupported');
});

test('family member sees mutation actions disabled on non-assigned recurrings', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListRecurrings::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('edit')->table($fixtures['own']))
        ->assertActionEnabled(TestAction::make('edit')->table($fixtures['own']))
        ->assertActionVisible(TestAction::make('delete')->table($fixtures['own']))
        ->assertActionEnabled(TestAction::make('delete')->table($fixtures['own']))
        ->assertActionVisible(TestAction::make('edit')->table($fixtures['primary']))
        ->assertActionDisabled(TestAction::make('edit')->table($fixtures['primary']))
        ->assertActionVisible(TestAction::make('delete')->table($fixtures['shared']))
        ->assertActionDisabled(TestAction::make('delete')->table($fixtures['shared']));
});

test('family member cannot restore or force delete non-assigned recurrings', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $fixtures['own']->delete();
    $fixtures['primary']->delete();

    Livewire::test(ListRecurrings::class)
        ->filterTable('trashed', false)
        ->assertSuccessful()
        ->assertActionEnabled(TestAction::make('restore')->table($fixtures['own']))
        ->assertActionEnabled(TestAction::make('forceDelete')->table($fixtures['own']))
        ->assertActionDisabled(TestAction::make('restore')->table($fixtures['primary']))
        ->assertActionDisabled(TestAction::make('forceDelete')->table($fixtures['primary']));
});

test('family member cannot follow a row edit link for non-assigned recurrings', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $table = Livewire::test(ListRecurrings::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    expect($table->getRecordUrl($fixtures['own']))
        ->toBe(RecurringResource::getUrl('edit', ['record' => $fixtures['own']]))
        ->and($table->getRecordUrl($fixtures['primary']))->toBeNull()
        ->and($table->getRecordUrl($fixtures['shared']))->toBeNull();
});

test('family member edit cannot reassign a recurring', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(EditRecurring::class, ['record' => $fixtures['own']->getRouteKey()])
        ->fillForm([
            'expected_amount' => 42.50,
            'responsibility' => 'household_shared',
            'family_member_id' => $fixtures['other']->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fixtures['own']->refresh();

    expect((int) $fixtures['own']->family_member_id)->toBe((int) $fixtures['member']->id)
        ->and($fixtures['own']->is_shared)->toBeFalse()
        ->and((float) $fixtures['own']->expected_amount)->toBe(42.50);
});

test('recurring resource canEdit matches assignment', function () {
    $fixtures = recurringOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(RecurringResource::canEdit($fixtures['own']))->toBeTrue()
        ->and(RecurringResource::canEdit($fixtures['primary']))->toBeFalse()
        ->and(RecurringResource::canDelete($fixtures['otherOwned']))->toBeFalse()
        ->and(RecurringResource::getGlobalSearchResultUrl($fixtures['own']))
        ->toBe(RecurringResource::getUrl('edit', ['record' => $fixtures['own']]))
        ->and(RecurringResource::getGlobalSearchResultUrl($fixtures['primary']))
        ->toBe(RecurringResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $fixtures['primary']->getRouteKey(),
        ]));
});
