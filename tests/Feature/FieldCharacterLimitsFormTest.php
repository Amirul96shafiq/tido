<?php

declare(strict_types=1);

use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\FamilyMembers\Pages\CreateFamilyMember;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Models\Label;
use App\Models\User;
use App\Support\FieldCharacterLimits;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('text fields expose character limits', function (string $page, string $field, int $max) {
    Livewire::test($page)
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            $field,
            checkComponentUsing: fn (TextInput $component): bool => $component->getMaxLength() === $max,
        );
})->with([
    'profile full name' => [EditProfile::class, 'name', FieldCharacterLimits::USER_NAME],
    'profile display name' => [EditProfile::class, 'display_name', FieldCharacterLimits::DISPLAY_NAME],
    'family member full name' => [CreateFamilyMember::class, 'name', FieldCharacterLimits::USER_NAME],
    'family member display name' => [CreateFamilyMember::class, 'display_name', FieldCharacterLimits::DISPLAY_NAME],
    'family member custom relationship' => [CreateFamilyMember::class, 'relationship_other', FieldCharacterLimits::RELATIONSHIP_OTHER],
    'expense merchant' => [CreateExpense::class, 'merchant_name', FieldCharacterLimits::MERCHANT_NAME],
    'budget title' => [CreateBudget::class, 'title', FieldCharacterLimits::BUDGET_TITLE],
    'recurring title' => [CreateRecurring::class, 'title', FieldCharacterLimits::RECURRING_TITLE],
    'label name' => [CreateLabel::class, 'name', FieldCharacterLimits::LABEL_NAME],
    'payment method name' => [CreatePaymentMethod::class, 'name', FieldCharacterLimits::PAYMENT_METHOD_NAME],
]);

test('notes fields expose the shared plaintext limit', function (string $page, string $field) {
    Livewire::test($page)
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            $field,
            checkComponentUsing: fn (NotesRichEditor $component): bool => $component->getMaxLength() === FieldCharacterLimits::NOTES,
        );
})->with([
    'expense notes' => [CreateExpense::class, 'notes'],
    'budget notes' => [CreateBudget::class, 'notes'],
    'recurring notes' => [CreateRecurring::class, 'notes'],
    'label notes' => [CreateLabel::class, 'description'],
    'payment method notes' => [CreatePaymentMethod::class, 'notes'],
]);

test('profile rejects a full name over the character limit', function () {
    Livewire::test(EditProfile::class)
        ->set('data.name', str_repeat('a', FieldCharacterLimits::USER_NAME + 1))
        ->call('save')
        ->assertHasErrors(['data.name']);
});

test('profile saves a full name at the character limit', function () {
    Livewire::test(EditProfile::class)
        ->set('data.name', str_repeat('a', FieldCharacterLimits::USER_NAME))
        ->call('save')
        ->assertHasNoErrors();
});

test('expense create rejects a merchant name over the character limit', function () {
    Queue::fake();
    $label = Label::factory()->create();

    Livewire::test(CreateExpense::class)
        ->fillForm([
            'merchant_name' => str_repeat('m', FieldCharacterLimits::MERCHANT_NAME + 1),
            'date_time' => now()->toDateTimeString(),
            'subtotal' => '1.00',
            'total_amount' => '1.00',
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
            'expenseItems' => [[
                'description' => 'Item name',
                'label_id' => $label->id,
                'quantity' => 1,
                'unit_price' => '1.00',
                'line_total' => '1.00',
            ]],
        ])
        ->call('create')
        ->assertHasFormErrors(['merchant_name']);
});

test('expense create rejects a line item description over the character limit', function () {
    Queue::fake();
    $label = Label::factory()->create();

    $component = Livewire::test(CreateExpense::class)
        ->fillForm([
            'merchant_name' => 'Store',
            'date_time' => now()->toDateTimeString(),
            'subtotal' => '1.00',
            'total_amount' => '1.00',
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
            'expenseItems' => [[
                'description' => 'Item name',
                'label_id' => $label->id,
                'quantity' => 1,
                'unit_price' => '1.00',
                'line_total' => '1.00',
            ]],
        ]);

    $itemKey = array_key_first($component->get('data.expenseItems') ?? []);

    $component
        ->set("data.expenseItems.{$itemKey}.description", str_repeat('d', FieldCharacterLimits::LINE_ITEM_DESCRIPTION + 1))
        ->call('create')
        ->assertHasFormErrors(["expenseItems.{$itemKey}.description"]);
});

test('label create rejects notes over the plaintext character limit', function () {
    Livewire::test(CreateLabel::class)
        ->fillForm([
            'name' => 'Pet Supplies',
            'slug' => 'pet-supplies-limit',
            'description' => '<p>'.str_repeat('n', FieldCharacterLimits::NOTES + 1).'</p>',
        ])
        ->call('create')
        ->assertHasFormErrors(['description']);
});

test('label create saves notes at the plaintext character limit', function () {
    Livewire::test(CreateLabel::class)
        ->fillForm([
            'name' => 'Pet Supplies',
            'slug' => 'pet-supplies-at-limit',
            'description' => '<p>'.str_repeat('n', FieldCharacterLimits::NOTES).'</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});
