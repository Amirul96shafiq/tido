<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Models\Budget;
use App\Models\ContentDraft;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    Queue::fake();

    $this->user = User::factory()->withWhatsAppPhone('60123456789')->create();
    $this->actingAs($this->user);
});

test('saveDraft persists meaningful create form payload', function () {
    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'merchant_name' => 'FamilyMart Pinggiran',
            'notes' => 'Keep this draft',
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'invoice-create')
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['merchant_name'])->toBe('FamilyMart Pinggiran')
        ->and(data_get($draft->payload, 'notes.content.0.content.0.text'))->toBe('Keep this draft')
        ->and($draft->payload)->not->toHaveKey('image_path');
});

test('saveDraft does not persist an empty create form', function () {
    Livewire::test(CreateInvoice::class)
        ->call('saveDraft');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'invoice-create')
            ->exists()
    )->toBeFalse();
});

test('create page offers draft recovery and restore fills the form', function () {
    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'invoice-create',
        'payload' => [
            'merchant_name' => 'Restored Merchant',
            'notes' => 'From draft',
            'image_path' => 'should-be-ignored.jpg',
        ],
    ]);

    Livewire::test(CreateInvoice::class)
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
        'key' => 'invoice-create',
        'payload' => [
            'merchant_name' => 'Discard Me',
        ],
    ]);

    Livewire::test(CreateInvoice::class)
        ->assertNotified('Unsaved draft found')
        ->dispatch('discard-content-draft')
        ->assertNotified('Draft discarded');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'invoice-create')
            ->exists()
    )->toBeFalse();
});

test('successful create clears the draft', function () {
    $label = Label::factory()->create([
        'type' => LabelType::Finance,
    ]);

    ContentDraft::factory()->create([
        'user_id' => $this->user->id,
        'key' => 'invoice-create',
        'payload' => [
            'merchant_name' => 'Will Be Cleared',
        ],
    ]);

    Livewire::test(CreateInvoice::class)
        ->set('data.invoiceItems', [])
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
            'invoiceItems' => [
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

    expect(Invoice::query()->where('merchant_name', 'Manual Store')->exists())->toBeTrue()
        ->and(
            ContentDraft::query()
                ->where('user_id', $this->user->id)
                ->where('key', 'invoice-create')
                ->exists()
        )->toBeFalse();
});

test('edit page does not keep a recoverable draft when form is unchanged', function () {
    $invoice = Invoice::factory()->create([
        'merchant_name' => 'Original Merchant',
        'notes' => 'Original notes',
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->call('saveDraft');

    expect(
        ContentDraft::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'invoice-edit-'.$invoice->getKey())
            ->exists()
    )->toBeFalse();
});

test('edit page saves a draft when the form is dirty', function () {
    $invoice = Invoice::factory()->create([
        'merchant_name' => 'Original Merchant',
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->fillForm([
            'merchant_name' => 'Updated Merchant Draft',
        ])
        ->call('saveDraft');

    $draft = ContentDraft::query()
        ->where('user_id', $this->user->id)
        ->where('key', 'invoice-edit-'.$invoice->getKey())
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['merchant_name'])->toBe('Updated Merchant Draft');
});

test('successful edit save clears the draft', function () {
    $invoice = Invoice::factory()->create([
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
        'key' => 'invoice-edit-'.$invoice->getKey(),
        'payload' => [
            'merchant_name' => 'Stale Draft',
        ],
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->fillForm([
            'merchant_name' => 'Saved Merchant',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($invoice->fresh()->merchant_name)->toBe('Saved Merchant')
        ->and(
            ContentDraft::query()
                ->where('user_id', $this->user->id)
                ->where('key', 'invoice-edit-'.$invoice->getKey())
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
            'description' => 'From draft',
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
