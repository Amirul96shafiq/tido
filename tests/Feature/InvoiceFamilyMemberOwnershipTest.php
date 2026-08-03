<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\User;
use App\Support\HouseholdAccess;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{member: FamilyMember, other: FamilyMember, user: User, own: Invoice, primary: Invoice, otherOwned: Invoice}
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

    Invoice::unsetEventDispatcher();

    $own = Invoice::factory()->create([
        'merchant_name' => 'Own Merchant',
        'family_member_id' => $member->id,
        'image_path' => 'receipts/own.jpg',
    ]);
    $primary = Invoice::factory()->create([
        'merchant_name' => 'Primary Merchant',
        'family_member_id' => null,
        'image_path' => 'receipts/primary.jpg',
    ]);
    $otherOwned = Invoice::factory()->create([
        'merchant_name' => 'Other Merchant',
        'family_member_id' => $other->id,
        'image_path' => 'receipts/other.jpg',
    ]);

    Invoice::setEventDispatcher(app('events'));

    return [
        'member' => $member,
        'other' => $other,
        'user' => $user,
        'own' => $own,
        'primary' => $primary,
        'otherOwned' => $otherOwned,
    ];
}

test('household access allows family member to mutate only own invoices', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(HouseholdAccess::canMutateInvoice($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateInvoice($fixtures['primary']))->toBeFalse()
        ->and(HouseholdAccess::canMutateInvoice($fixtures['otherOwned']))->toBeFalse();
});

test('primary user can mutate any invoice', function () {
    $fixtures = ownershipFixtures();
    $primaryUser = User::factory()->create();

    $this->actingAs($primaryUser);

    expect(HouseholdAccess::canMutateInvoice($fixtures['own']))->toBeTrue()
        ->and(HouseholdAccess::canMutateInvoice($fixtures['primary']))->toBeTrue()
        ->and(HouseholdAccess::canMutateInvoice($fixtures['otherOwned']))->toBeTrue();
});

test('family member can open edit page for own invoice only', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(EditInvoice::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditInvoice::class, ['record' => $fixtures['primary']->getRouteKey()])
        ->assertForbidden();

    Livewire::test(EditInvoice::class, ['record' => $fixtures['otherOwned']->getRouteKey()])
        ->assertForbidden();
});

test('primary user can open edit page for any invoice', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs(User::factory()->create());

    Livewire::test(EditInvoice::class, ['record' => $fixtures['own']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditInvoice::class, ['record' => $fixtures['primary']->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditInvoice::class, ['record' => $fixtures['otherOwned']->getRouteKey()])
        ->assertSuccessful();
});

test('family member cannot select non-owned invoices for bulk actions', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    $table = Livewire::test(ListInvoices::class)
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

test('family member sees mutation actions disabled on non-owned invoices', function () {
    Storage::fake('local');
    Storage::put('receipts/own.jpg', 'fake');
    Storage::put('receipts/primary.jpg', 'fake');
    Storage::put('receipts/other.jpg', 'fake');

    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    Livewire::test(ListInvoices::class)
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

test('primary user keeps mutation actions enabled for every invoice', function () {
    Storage::fake('local');
    Storage::put('receipts/own.jpg', 'fake');
    Storage::put('receipts/primary.jpg', 'fake');
    Storage::put('receipts/other.jpg', 'fake');

    $fixtures = ownershipFixtures();

    $this->actingAs(User::factory()->create());

    $component = Livewire::test(ListInvoices::class)
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

    Livewire::test(CreateInvoice::class)
        ->set('data.invoiceItems', [])
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
            'invoiceItems' => [
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

    $created = Invoice::query()
        ->where('merchant_name', 'Forced Ownership Store')
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->family_member_id)->toBe($fixtures['member']->id);
});

test('invoice resource canEdit matches ownership', function () {
    $fixtures = ownershipFixtures();

    $this->actingAs($fixtures['user']);

    expect(InvoiceResource::canEdit($fixtures['own']))->toBeTrue()
        ->and(InvoiceResource::canEdit($fixtures['primary']))->toBeFalse()
        ->and(InvoiceResource::canDelete($fixtures['otherOwned']))->toBeFalse();
});
