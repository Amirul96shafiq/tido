<?php

declare(strict_types=1);

use App\Filament\Widgets\TopMerchants;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('top merchants widget truncates long merchant labels', function () {
    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'merchant_name' => 'Cosmo Restaurants Sdn Bhd',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 50.00,
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertSee('borderRadius', false)
        ->assertSee("pointStyle: 'circle'", false)
        ->assertSee('pointStyleWidth: 14', false)
        ->assertSee('boxHeight: 10', false)
        ->assertSee('Cosmo Rest... (1)');
});

test('top merchants widget leaves short merchant labels unchanged', function () {
    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'merchant_name' => '7-Eleven',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 12.50,
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertSee('7-Eleven (1)');
});

test('top merchants widget shows receipt count on axis labels', function () {
    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'merchant_name' => 'Grocery Mart',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 40.00,
    ]);

    Expense::factory()->create([
        'merchant_name' => 'Grocery Mart',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 20.00,
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertSee('Grocery Ma... (2)')
        ->assertDontSee('saved this month');
});

test('top merchants widget renders empty state', function () {
    Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertSee('No merchants')
        ->assertSee('No merchant spending recorded for this month.')
        ->assertSee('Upload Receipts');
});

test('top merchants widget listens for echo expense updates without polling', function () {
    $component = Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.30s')
        ->assertDontSeeHtml('wire:poll.5s');

    expect($component->instance()->getListeners())
        ->toHaveKey('echo-private:household.expenses,.ExpenseUpdated');
});
