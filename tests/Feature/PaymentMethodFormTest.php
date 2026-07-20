<?php

declare(strict_types=1);

use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('payment method form uses notes rich editor for notes', function () {
    Livewire::test(CreatePaymentMethod::class)
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'notes',
            checkComponentUsing: function (NotesRichEditor $component): bool {
                expect($component->getExtraAttributes())->toMatchArray([
                    'class' => NotesRichEditor::EXTRA_CLASS,
                ]);

                return true;
            },
        );
});

test('payment method name and slug have empty placeholders', function () {
    Livewire::test(CreatePaymentMethod::class)
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'name',
            checkComponentUsing: fn (TextInput $component): bool => $component->getPlaceholder() === 'Payment method name',
        )
        ->assertSchemaComponentExists(
            'slug',
            checkComponentUsing: fn (TextInput $component): bool => $component->getPlaceholder() === 'payment-method-slug',
        )
        ->assertSchemaComponentExists(
            'aliases',
            checkComponentUsing: fn (TagsInput $component): bool => $component->getPlaceholder() === 'Add alias (e.g. grabpay)',
        );
});

test('create payment method page saves aliases normalized', function () {
    Livewire::test(CreatePaymentMethod::class)
        ->fillForm([
            'name' => 'GrabPay',
            'slug' => 'grabpay',
            'aliases' => ['Grab Pay', 'GRAB'],
            'notes' => '<p>Grab e-wallet payments</p>',
            'icon' => 'heroicon-o-device-phone-mobile',
            'color' => '#00B14F',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $method = PaymentMethod::query()->where('slug', 'grabpay')->first();

    expect($method)->not->toBeNull()
        ->and($method->is_system)->toBeFalse()
        ->and($method->aliases)->toBe(['grab_pay', 'grab'])
        ->and($method->notes)->toBe('<p>Grab e-wallet payments</p>');
});

test('system payment method slug is locked on edit', function () {
    $this->seed(PaymentMethodSeeder::class);
    $cash = PaymentMethod::findBySlug('cash');

    expect($cash)->not->toBeNull();

    Livewire::test(EditPaymentMethod::class, ['record' => $cash->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'slug',
            checkComponentUsing: fn (TextInput $component): bool => $component->isDisabled(),
        );
});
