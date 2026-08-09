<?php

use App\Models\Backup;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->primary = User::factory()->create();
    $this->actingAs($this->primary);
});

test('resource models stamp the authenticated editor on create and update', function (string $modelClass, array $updates): void {
    $record = $modelClass::factory()->create();

    expect($record->edited_by)->toBe($this->primary->getKey());

    $familyMember = FamilyMember::factory()->create();
    $familyUser = User::factory()->familyMember($familyMember->getKey())->create();
    $this->actingAs($familyUser);

    $record->update($updates);
    $record->refresh();

    expect($record->edited_by)->toBe($familyUser->getKey())
        ->and($record->editedBy->is($familyUser))->toBeTrue();
})->with([
    'backups' => [Backup::class, ['filename' => 'edited-backup.zip']],
    'budgets' => [Budget::class, ['title' => 'Edited budget']],
    'family members' => [FamilyMember::class, ['display_name' => 'Edited member']],
    'expenses' => [Expense::class, ['merchant_name' => 'Edited merchant']],
    'labels' => [Label::class, ['name' => 'Edited label']],
    'payment methods' => [PaymentMethod::class, ['name' => 'Edited payment method']],
]);

test('resource models leave editor empty for system changes', function (): void {
    $label = Label::factory()->create();
    auth()->logout();

    $label->update(['name' => 'System label update']);
    $label->refresh();

    expect($label->edited_by)->toBeNull()
        ->and($label->editedBy)->toBeNull();
});
