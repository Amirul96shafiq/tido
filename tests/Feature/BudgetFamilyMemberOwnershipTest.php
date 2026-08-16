<?php

declare(strict_types=1);

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Models\Budget;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\HouseholdAccess;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{member: FamilyMember, other: FamilyMember, user: User, own: Budget, primary: Budget, otherOwned: Budget, shared: Budget}
 */
function budgetOwnershipFixtures(): array
{
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60111111111',
    ]);
    $other = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60122222222',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $own = Budget::factory()->forFamilyMember($member)->create([
        'title' => 'Own Budget',
        'amount' => 100,
    ]);
    $primary = Budget::factory()->create([
        'title' => 'Primary Budget',
        'family_member_id' => null,
        'amount' => 200,
    ]);
    $otherOwned = Budget::factory()->forFamilyMember($other)->create([
        'title' => 'Other Budget',
        'amount' => 300,
    ]);
    $shared = Budget::factory()->shared()->create([
        'title' => 'Shared Budget',
        'family_member_id' => null,
        'amount' => 400,
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

test('household access allows family member to mutate only assigned budgets', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(HouseholdAccess::canMutateBudget($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateBudget($fixtures['primary']))->toBeFalse()
        ->and(HouseholdAccess::canMutateBudget($fixtures['otherOwned']))->toBeFalse()
        ->and(HouseholdAccess::canMutateBudget($fixtures['shared']))->toBeFalse();
});

test('primary user can mutate any budget', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs(User::factory()->create());

    expect(HouseholdAccess::canMutateBudget($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateBudget($fixtures['primary']))->toBeTrue()
        ->and(HouseholdAccess::canMutateBudget($fixtures['otherOwned']))->toBeTrue()
        ->and(HouseholdAccess::canMutateBudget($fixtures['shared']))->toBeTrue();
});

test('family member can list every household budget', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $this->get(BudgetResource::getUrl('index'))
        ->assertOk();

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([
            $fixtures['own'],
            $fixtures['primary'],
            $fixtures['otherOwned'],
            $fixtures['shared'],
        ]);
});

test('family member can open edit page for assigned budget only', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(EditBudget::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditBudget::class, ['record' => $fixtures['primary']->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(EditBudget::class, ['record' => $fixtures['otherOwned']->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(EditBudget::class, ['record' => $fixtures['shared']->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('family member cannot open the create budget page', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $this->get(BudgetResource::getUrl('create'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(CreateBudget::class)
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    expect(BudgetResource::canCreate())->toBeFalse();
});

test('family member sees create budget action visible and disabled', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertActionVisible('create')
        ->assertActionDisabled('create');
});

test('family member sees duplicate actions visible and disabled', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('replicate')->table($fixtures['own']))
        ->assertActionDisabled(TestAction::make('replicate')->table($fixtures['own']))
        ->assertActionVisible(TestAction::make('duplicate')->table()->bulk())
        ->assertActionDisabled(TestAction::make('duplicate')->table()->bulk());

    Livewire::test(EditBudget::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('replicate')
        ->assertActionDisabled('replicate');

    expect(Budget::query()->count())->toBe(4);

    Livewire::test(ListBudgets::class)
        ->callAction(TestAction::make('replicate')->table($fixtures['own']));

    expect(Budget::query()->count())->toBe(4);
});

test('family member cannot select non-assigned budgets for bulk actions', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $table = Livewire::test(ListBudgets::class)
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

test('family member sees mutation actions disabled on non-assigned budgets', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListBudgets::class)
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

test('family member cannot follow a row edit link for non-assigned budgets', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    $table = Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    expect($table->getRecordUrl($fixtures['own']))
        ->toBe(BudgetResource::getUrl('edit', ['record' => $fixtures['own']]))
        ->and($table->getRecordUrl($fixtures['primary']))->toBeNull()
        ->and($table->getRecordUrl($fixtures['shared']))->toBeNull();
});

test('family member edit cannot reassign a budget', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(EditBudget::class, ['record' => $fixtures['own']->getRouteKey()])
        ->fillForm([
            'amount' => 175.00,
            'family_member_id' => $fixtures['other']->id,
            'is_shared' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fixtures['own']->refresh();

    expect((int) $fixtures['own']->family_member_id)->toBe((int) $fixtures['member']->id)
        ->and($fixtures['own']->is_shared)->toBeFalse()
        ->and((float) $fixtures['own']->amount)->toBe(175.00);
});

test('budget resource canEdit matches assignment', function () {
    $fixtures = budgetOwnershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(BudgetResource::canEdit($fixtures['own']))->toBeTrue()
        ->and(BudgetResource::canEdit($fixtures['primary']))->toBeFalse()
        ->and(BudgetResource::canDelete($fixtures['otherOwned']))->toBeFalse()
        ->and(BudgetResource::getGlobalSearchResultUrl($fixtures['own']))
        ->toBe(BudgetResource::getUrl('edit', ['record' => $fixtures['own']]))
        ->and(BudgetResource::getGlobalSearchResultUrl($fixtures['primary']))
        ->toBe(BudgetResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $fixtures['primary']->getRouteKey(),
        ]));
});
