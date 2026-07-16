<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Filament\Widgets\SpendingByPaymentMethod;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('spending by payment method widget renders with axis labels', function () {
    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'merchant_name' => 'Corner Shop',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 25.00,
        'payment_method' => PaymentMethod::Cash,
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(SpendingByPaymentMethod::class)
        ->assertSuccessful()
        ->assertSee('Cash (1)');
});

test('spending by payment method widget renders empty state', function () {
    Livewire::test(SpendingByPaymentMethod::class)
        ->assertSuccessful()
        ->assertSee('No expenses')
        ->assertSee('No payment method spending recorded for this month.')
        ->assertSee('Upload Receipts');
});
