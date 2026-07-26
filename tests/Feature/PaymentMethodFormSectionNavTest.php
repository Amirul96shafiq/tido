<?php

declare(strict_types=1);

use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Schemas\PaymentMethodForm;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('payment method create page renders sticky section nav markers', function () {
    Livewire::test(CreatePaymentMethod::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false)
        ->assertSee('tido-section-nav', false);
});

test('payment method edit page renders sticky section nav markers', function () {
    $paymentMethod = PaymentMethod::factory()->create();

    Livewire::test(EditPaymentMethod::class, ['record' => $paymentMethod->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('payment method section nav lists anchor tabs', function () {
    Livewire::test(CreatePaymentMethod::class)
        ->assertSuccessful()
        ->assertSee('Payment Method Appearance')
        ->assertSee('Payment Method Details')
        ->assertSee('Payment Method Notes')
        ->assertSee('#payment-method-appearance', false)
        ->assertSee('#payment-method-details', false)
        ->assertSee('#payment-method-notes', false);
});

test('payment method section nav items match sectionNavItems helper', function () {
    expect(PaymentMethodForm::sectionNavItems())->toBe([
        ['label' => 'Payment Method Appearance', 'id' => 'payment-method-appearance'],
        ['label' => 'Payment Method Details', 'id' => 'payment-method-details'],
        ['label' => 'Payment Method Notes', 'id' => 'payment-method-notes'],
    ]);
});

test('payment method section nav smooth scrolls on tab click', function () {
    Livewire::test(CreatePaymentMethod::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
