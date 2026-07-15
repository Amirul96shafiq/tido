<?php

declare(strict_types=1);

use App\Filament\Widgets\TopMerchants;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('top merchants widget truncates long merchant labels', function () {
    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'merchant_name' => 'Cosmo Restaurants Sdn Bhd',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 50.00,
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertSee('Cosmo Rest... (1)');
});

test('top merchants widget leaves short merchant labels unchanged', function () {
    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'merchant_name' => '7-Eleven',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 12.50,
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertSee('7-Eleven (1)');
});

test('top merchants widget shows receipt count on axis labels', function () {
    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'merchant_name' => 'Grocery Mart',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 40.00,
    ]);

    Invoice::factory()->create([
        'merchant_name' => 'Grocery Mart',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 20.00,
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(TopMerchants::class)
        ->assertSuccessful()
        ->assertSee('Grocery Ma... (2)')
        ->assertDontSee('saved this month');
});
