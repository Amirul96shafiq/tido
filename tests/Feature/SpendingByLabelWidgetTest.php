<?php

declare(strict_types=1);

use App\Filament\Widgets\SpendingByLabel;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('spending by label widget renders with enriched chart data', function () {
    Invoice::unsetEventDispatcher();

    $label = Label::factory()->create(['name' => 'Groceries', 'slug' => 'groceries']);

    $invoice = Invoice::factory()->create([
        'merchant_name' => 'Grocery Store',
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 55.00,
    ]);

    $invoice->invoiceItems()->create([
        'label_id' => $label->id,
        'description' => 'Vegetables',
        'quantity' => 1,
        'unit_price' => 55.00,
        'line_total' => 55.00,
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(SpendingByLabel::class)
        ->assertSuccessful();
});

test('spending by label widget renders empty state', function () {
    Livewire::test(SpendingByLabel::class)
        ->assertSuccessful()
        ->assertSee('No Expenses');
});
