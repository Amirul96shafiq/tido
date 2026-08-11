<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Models\Budget;
use App\Models\ContentDraft;
use App\Models\Expense;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

    Queue::fake();

    $this->user = User::factory()->withWhatsAppPhone('60123456789')->create();
    $this->actingAs($this->user);
});

test('saveDraft persists meaningful create form payload', function () {
    Livewire::test(CreateExpense::class)
        ->fillForm([
            'merchant_name' => 'FamilyMart Pinggiran',
            'notes' => 'Keep this draft',
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'expense-create')
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['merchant_name'])->toBe('FamilyMart Pinggiran')
        ->and(data_get($draft->payload, 'notes.content.0.content.0.text'))->toBe('Keep this draft')
        ->and($draft->payload)->not->toHaveKey('image_path');
});

test('saveDraft does not persist an empty create form', function () {
    Livewire::test(CreateExpense::class)
        ->call('saveDraft');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'expense-create')
            ->exists()
    )->toBeFalse();
});

test('create page offers draft recovery and restore fills the form', function () {
    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'expense-create',
        'payload' => [
            'merchant_name' => 'Restored Merchant',
            'notes' => 'From draft',
            'image_path' => 'should-be-ignored.jpg',
        ],
    ]);

    Livewire::test(CreateExpense::class)
        ->assertNotified('Unsaved draft found')
        ->dispatch('restore-content-draft')
        ->assertFormSet([
            'merchant_name' => 'Restored Merchant',
            'notes' => '<p>From draft</p>',
        ])
        ->assertNotified('Draft restored');
});

test('discard clears the draft and removes recovery prompt work', function () {
    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'expense-create',
        'payload' => [
            'merchant_name' => 'Discard Me',
        ],
    ]);

    Livewire::test(CreateExpense::class)
        ->assertNotified('Unsaved draft found')
        ->dispatch('discard-content-draft')
        ->assertNotified('Draft discarded');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'expense-create')
            ->exists()
    )->toBeFalse();
});

test('successful create clears the draft', function () {
    $label = Label::factory()->create([
        'type' => LabelType::Finance,
    ]);

    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'expense-create',
        'payload' => [
            'merchant_name' => 'Will Be Cleared',
        ],
    ]);

    Livewire::test(CreateExpense::class)
        ->set('data.expenseItems', [])
        ->fillForm([
            'merchant_name' => 'Manual Store',
            'invoice_number' => 'INV-DRAFT-1',
            'date_time' => now()->toDateTimeString(),
            'subtotal' => 10.00,
            'total_tax' => 0.00,
            'discount_total' => 0.00,
            'rounding_amount' => 0.00,
            'total_amount' => 10.00,
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
            'expenseItems' => [
                [
                    'description' => 'Nasi Lemak',
                    'label_id' => $label->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                    'line_total' => 10.00,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Expense::query()->where('merchant_name', 'Manual Store')->exists())->toBeTrue()
        ->and(
            ContentDraft::query()
                ->where('user_id', $this->user->id)
                ->where('key', 'expense-create')
                ->exists()
        )->toBeFalse();
});

test('edit page does not keep a recoverable draft when form is unchanged', function () {
    $expense = Expense::factory()->create([
        'merchant_name' => 'Original Merchant',
        'notes' => 'Original notes',
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->call('saveDraft');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'expense-edit-'.$expense->getKey())
            ->exists()
    )->toBeFalse();
});

test('edit page saves a draft when the form is dirty', function () {
    $expense = Expense::factory()->create([
        'merchant_name' => 'Original Merchant',
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->fillForm([
            'merchant_name' => 'Updated Merchant Draft',
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'expense-edit-'.$expense->getKey())
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['merchant_name'])->toBe('Updated Merchant Draft');
});

test('reverting an edit to its original state clears the saved draft indicator', function () {
    $expense = Expense::factory()->create([
        'merchant_name' => 'Original Merchant',
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->fillForm([
            'merchant_name' => 'Updated Merchant Draft',
        ])
        ->call('saveDraft')
        ->assertDispatched('content-draft-saved')
        ->fillForm([
            'merchant_name' => 'Original Merchant',
        ])
        ->call('saveDraft')
        ->assertDispatched('content-draft-cleared');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'expense-edit-'.$expense->getKey())
            ->exists()
    )->toBeFalse();
});

test('successful edit save prevents the draft from reappearing on the next autosave poll', function () {
    $expense = Expense::factory()->create([
        'merchant_name' => 'Original Merchant',
        'subtotal' => 10.00,
        'total_tax' => 0.00,
        'discount_total' => 0.00,
        'rounding_amount' => 0.00,
        'total_amount' => 10.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'expense-edit-'.$expense->getKey(),
        'payload' => [
            'merchant_name' => 'Stale Draft',
        ],
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->fillForm([
            'merchant_name' => 'Saved Merchant',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->call('saveDraft')
        ->assertNotDispatched('content-draft-saved');

    expect($expense->fresh()->merchant_name)->toBe('Saved Merchant')
        ->and(
            ContentDraft::query()
                ->where('user_id', $this->user->id)
                ->where('key', 'expense-edit-'.$expense->getKey())
                ->exists()
        )->toBeFalse();
});

test('label create saveDraft persists dirty form payload', function () {
    Livewire::test(CreateLabel::class)
        ->fillForm([
            'name' => 'Draft Label',
            'slug' => 'draft-label',
            'description' => 'Saved as draft',
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'label-create')
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['name'])->toBe('Draft Label')
        ->and($draft->payload['slug'])->toBe('draft-label');
});

test('label create page offers draft recovery', function () {
    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'label-create',
        'payload' => [
            'name' => 'Recovered Label',
            'slug' => 'recovered-label',
            'description' => 'From draft',
            'type' => LabelType::Finance->value,
        ],
    ]);

    Livewire::test(CreateLabel::class)
        ->assertNotified('Unsaved draft found')
        ->dispatch('restore-content-draft')
        ->assertFormSet([
            'name' => 'Recovered Label',
            'slug' => 'recovered-label',
            'description' => '<p>From draft</p>',
        ]);
});

test('label edit page saves a draft when the form is dirty', function () {
    $label = Label::factory()->create([
        'name' => 'Original Label',
        'slug' => 'original-label',
        'type' => LabelType::Finance,
    ]);

    Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Label Draft',
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'label-edit-'.$label->getKey())
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['name'])->toBe('Updated Label Draft');
});

test('budget create saveDraft persists dirty form payload', function () {
    Livewire::test(CreateBudget::class)
        ->fillForm([
            'amount' => 250.00,
            'period' => 'monthly',
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'budget-create')
        ->first();

    expect($draft)->not->toBeNull()
        ->and((float) $draft->payload['amount'])->toBe(250.0)
        ->and($draft->payload['period'])->toBe('monthly');
});

test('budget edit page does not keep a draft when form is unchanged', function () {
    $budget = Budget::factory()->create([
        'amount' => 100.00,
        'period' => 'monthly',
    ]);

    Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()])
        ->call('saveDraft');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'budget-edit-'.$budget->getKey())
            ->exists()
    )->toBeFalse();
});

test('budget edit page saves a draft when the form is dirty', function () {
    $budget = Budget::factory()->create([
        'amount' => 100.00,
        'period' => 'monthly',
    ]);

    Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()])
        ->fillForm([
            'amount' => 500.00,
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'budget-edit-'.$budget->getKey())
        ->first();

    expect($draft)->not->toBeNull()
        ->and((float) $draft->payload['amount'])->toBe(500.0);
});

test('edit profile does not keep a recoverable draft when form is unchanged', function () {
    Livewire::test(EditProfile::class)
        ->call('saveDraft');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'profile-edit')
            ->exists()
    )->toBeFalse();
});

test('edit profile saves a draft when the form is dirty', function () {
    Livewire::test(EditProfile::class)
        ->set('data.display_name', 'Updated Display Draft')
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'profile-edit')
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['display_name'])->toBe('Updated Display Draft');
});

test('edit profile draft excludes password fields', function () {
    Livewire::test(EditProfile::class)
        ->set('data.change_password', true)
        ->set('data.currentPassword', 'secret-password')
        ->set('data.password', 'new-password-123')
        ->set('data.passwordConfirmation', 'new-password-123')
        ->set('data.display_name', 'Draft With Password Change')
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'profile-edit')
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['display_name'])->toBe('Draft With Password Change')
        ->and($draft->payload)->not->toHaveKey('password')
        ->and($draft->payload)->not->toHaveKey('passwordConfirmation')
        ->and($draft->payload)->not->toHaveKey('currentPassword')
        ->and($draft->payload)->not->toHaveKey('change_password');
});

test('edit profile offers draft recovery and restore fills the form', function () {
    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'profile-edit',
        'payload' => [
            'display_name' => 'From Draft',
        ],
    ]);

    Livewire::test(EditProfile::class)
        ->assertNotified('Unsaved draft found')
        ->dispatch('restore-content-draft')
        ->assertSet('data.display_name', 'From Draft')
        ->assertNotified('Draft restored');
});

test('successful profile save clears the draft', function () {
    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'profile-edit',
        'payload' => [
            'display_name' => 'Stale Draft',
        ],
    ]);

    Livewire::test(EditProfile::class)
        ->set('data.display_name', 'Saved Name')
        ->call('save')
        ->assertHasNoErrors();

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'profile-edit')
            ->exists()
    )->toBeFalse();
});
