<?php

declare(strict_types=1);

use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\FamilyMembers\Pages\ListFamilyMembers;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\Backup;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{member: FamilyMember, user: User}
 */
function settingsFamilyMemberUser(): array
{
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60133334444',
    ]);

    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    return [
        'member' => $member,
        'user' => $user,
    ];
}

test('family member can list labels and view but cannot create or edit', function () {
    $fixtures = settingsFamilyMemberUser();
    $label = Label::factory()->create(['name' => 'Household Label']);

    $this->actingAs($fixtures['user']);

    $this->get(LabelResource::getUrl('index'))
        ->assertSuccessful();

    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$label])
        ->assertActionVisible('create')
        ->assertActionDisabled('create')
        ->mountTableAction('view', $label)
        ->assertSuccessful();

    $this->get(LabelResource::getUrl('create'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('family member can list payment methods and view but cannot create or edit', function () {
    $fixtures = settingsFamilyMemberUser();
    $paymentMethod = PaymentMethod::factory()->create(['name' => 'Household Card']);

    $this->actingAs($fixtures['user']);

    $this->get(PaymentMethodResource::getUrl('index'))
        ->assertSuccessful();

    Livewire::test(ListPaymentMethods::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$paymentMethod])
        ->assertActionVisible('create')
        ->assertActionDisabled('create')
        ->mountTableAction('view', $paymentMethod)
        ->assertSuccessful();

    $this->get(PaymentMethodResource::getUrl('create'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    Livewire::test(EditPaymentMethod::class, ['record' => $paymentMethod->getRouteKey()])
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('family member can list family members and view but cannot create', function () {
    $fixtures = settingsFamilyMemberUser();
    $otherMember = FamilyMember::factory()->create(['name' => 'Sibling Member']);

    $this->actingAs($fixtures['user']);

    $this->get(FamilyMemberResource::getUrl('index'))
        ->assertSuccessful();

    Livewire::test(ListFamilyMembers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$fixtures['member'], $otherMember])
        ->assertActionVisible('create')
        ->assertActionDisabled('create')
        ->mountTableAction('view', $otherMember)
        ->assertSuccessful();

    $this->get(FamilyMemberResource::getUrl('create'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('family member can list backups but cannot create backup', function () {
    $fixtures = settingsFamilyMemberUser();
    $backup = Backup::factory()->create();

    $this->actingAs($fixtures['user']);

    $this->get(BackupResource::getUrl('index'))
        ->assertSuccessful();

    Livewire::test(ListBackups::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$backup])
        ->assertActionVisible('createBackup')
        ->assertActionDisabled('createBackup');
});
