<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\User;
use App\Support\HouseholdAccess;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{member: FamilyMember, other: FamilyMember, user: User, own: Expense, primary: Expense, otherOwned: Expense}
 */
function ownershipFixtures(): array
{
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60111111111',
    ]);
    $other = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60122222222',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    Expense::unsetEventDispatcher();

    $own = Expense::factory()->create([
        'merchant_name' => 'Own Merchant',
        'family_member_id' => $member->id,
        'image_path' => 'receipts/own.jpg',
    ]);
    $primary = Expense::factory()->create([
        'merchant_name' => 'Primary Merchant',
        'family_member_id' => null,
        'image_path' => 'receipts/primary.jpg',
    ]);
    $otherOwned = Expense::factory()->create([
        'merchant_name' => 'Other Merchant',
        'family_member_id' => $other->id,
        'image_path' => 'receipts/other.jpg',
    ]);

    Expense::setEventDispatcher(app('events'));

    return [
        'member' => $member,
        'other' => $other,
        'user' => $user,
        'own' => $own,
        'primary' => $primary,
        'otherOwned' => $otherOwned,
    ];
}

test('household access allows family member to mutate only own expenses', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(HouseholdAccess::canMutateExpense($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateExpense($fixtures['primary']))->toBeFalse()
        ->and(HouseholdAccess::canMutateExpense($fixtures['otherOwned']))->toBeFalse();
});

test('primary user can mutate any expense', function () {
    $fixtures = ownershipFixtures();
    $primaryUser = User::factory()->create();

    $this->actingAs($primaryUser);

    expect(HouseholdAccess::canMutateExpense($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateExpense($fixtures['primary']))->toBeTrue()
        ->and(HouseholdAccess::canMutateExpense($fixtures['otherOwned']))->toBeTrue();
});

test('family member can open edit page for own expense only', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(EditExpense::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditExpense::class, ['record' => $fixtures['primary']->getRouteKey()])
        ->assertForbidden();

    Livewire::test(EditExpense::class, ['record' => $fixtures['otherOwned']->getRouteKey()])
        ->assertForbidden();
});

test('primary user can open edit page for any expense', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs(User::factory()->create());

    Livewire::test(EditExpense::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditExpense::class, ['record' => $fixtures['primary']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditExpense::class, ['record' => $fixtures['otherOwned']->getRouteKey()])
        ->assertSuccessful();
});

test('family member cannot select non-owned expenses for bulk actions', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    $table = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    expect($table->isRecordSelectable($fixtures['own']))->toBeTrue()
        ->and($table->isRecordSelectable($fixtures['primary']))->toBeFalse()
        ->and($table->isRecordSelectable($fixtures['otherOwned']))->toBeFalse()
        ->and($table->getRecordClasses($fixtures['own']))->not->toContain('tido-ta-record-unsupported')
        ->and($table->getRecordClasses($fixtures['primary']))->toContain('tido-ta-record-unsupported')
        ->and($table->getRecordClasses($fixtures['otherOwned']))->toContain('tido-ta-record-unsupported');
});

test('family member sees mutation actions disabled on non-owned expenses', function () {
    Storage::fake('local');
    Storage::put('receipts/own.jpg', 'fake');
    Storage::put('receipts/primary.jpg', 'fake');
    Storage::put('receipts/other.jpg', 'fake');

    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('edit')->table($fixtures['own']))
        ->assertActionEnabled(TestAction::make('edit')->table($fixtures['own']))
        ->assertActionVisible(TestAction::make('delete')->table($fixtures['own']))
        ->assertActionEnabled(TestAction::make('delete')->table($fixtures['own']))
        ->assertActionVisible(TestAction::make('reparse')->table($fixtures['own']))
        ->assertActionEnabled(TestAction::make('reparse')->table($fixtures['own']))
        ->assertActionVisible(TestAction::make('edit')->table($fixtures['primary']))
        ->assertActionDisabled(TestAction::make('edit')->table($fixtures['primary']))
        ->assertActionVisible(TestAction::make('delete')->table($fixtures['primary']))
        ->assertActionDisabled(TestAction::make('delete')->table($fixtures['primary']))
        ->assertActionVisible(TestAction::make('reparse')->table($fixtures['primary']))
        ->assertActionDisabled(TestAction::make('reparse')->table($fixtures['primary']))
        ->assertActionVisible(TestAction::make('edit')->table($fixtures['otherOwned']))
        ->assertActionDisabled(TestAction::make('edit')->table($fixtures['otherOwned']))
        ->assertActionVisible(TestAction::make('delete')->table($fixtures['otherOwned']))
        ->assertActionDisabled(TestAction::make('delete')->table($fixtures['otherOwned']))
        ->assertActionVisible(TestAction::make('reparse')->table($fixtures['otherOwned']))
        ->assertActionDisabled(TestAction::make('reparse')->table($fixtures['otherOwned']));
});

test('family member cannot follow a row edit link for non-owned expenses', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    $table = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    expect($table->getRecordUrl($fixtures['own']))
        ->toBe(ExpenseResource::getUrl('edit', ['record' => $fixtures['own']]));

    expect($table->getRecordUrl($fixtures['primary']))
        ->toBeNull()
        ->and($table->getRecordUrl($fixtures['otherOwned']))->toBeNull();
});

test('primary user keeps mutation actions enabled for every expense', function () {
    Storage::fake('local');
    Storage::put('receipts/own.jpg', 'fake');
    Storage::put('receipts/primary.jpg', 'fake');
    Storage::put('receipts/other.jpg', 'fake');

    $fixtures = ownershipFixtures();

    $this->actingAs(User::factory()->create());

    $component = Livewire::test(ListExpenses::class)
        ->assertSuccessful();

    foreach (['own', 'primary', 'otherOwned'] as $fixtureKey) {
        foreach (['edit', 'delete', 'reparse'] as $actionName) {
            $component->assertActionEnabled(TestAction::make($actionName)->table($fixtures[$fixtureKey]));
        }
    }
});

test('family member create forces their family_member_id', function () {
    $fixtures = ownershipFixtures();
    $label = Label::factory()->create([
        'type' => LabelType::Finance,
    ]);

    $this->actingAs($fixtures['user']);

    Livewire::test(CreateExpense::class)
        ->set('data.expenseItems', [])
        ->fillForm([
            'merchant_name' => 'Forced Ownership Store',
            'date_time' => now()->toDateTimeString(),
            'subtotal' => 10,
            'total_tax' => 0,
            'discount_total' => 0,
            'rounding_amount' => 0,
            'total_amount' => 10,
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
            'family_member_id' => $fixtures['other']->id,
            'expenseItems' => [
                [
                    'description' => 'Item',
                    'label_id' => $label->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                    'line_total' => 10,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Expense::query()
        ->where('merchant_name', 'Forced Ownership Store')
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->family_member_id)->toBe($fixtures['member']->id);
});

test('expense resource canEdit matches ownership', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(ExpenseResource::canEdit($fixtures['own']))->toBeTrue()
        ->and(ExpenseResource::canEdit($fixtures['primary']))->toBeFalse()
        ->and(ExpenseResource::canDelete($fixtures['otherOwned']))->toBeFalse();
});
