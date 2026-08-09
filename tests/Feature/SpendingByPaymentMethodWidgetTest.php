<?php

declare(strict_types=1);

use App\Filament\Widgets\SpendingByPaymentMethod;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('spending by payment method widget renders with axis labels', function () {
    Expense::unsetEventDispatcher();

    $this->seed(PaymentMethodSeeder::class);

    Expense::factory()->create([
        'merchant_name' => 'Corner Shop',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 25.00,
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(SpendingByPaymentMethod::class)
        ->assertSuccessful()
        ->assertSee('borderRadius', false)
        ->assertSee('Cash (1)')
        ->assertSeeHtml('wire:poll.30s');
});

test('spending by payment method widget polls for live updates', function () {
    Livewire::test(SpendingByPaymentMethod::class)
        ->assertSuccessful()
        ->assertSeeHtml('wire:poll.30s="updateChartData"');
});

test('spending by payment method widget renders empty state', function () {
    Livewire::test(SpendingByPaymentMethod::class)
        ->assertSuccessful()
        ->assertSee('No expenses')
        ->assertSee('No payment method spending recorded for this month.')
        ->assertSee('Upload Receipts');
});
