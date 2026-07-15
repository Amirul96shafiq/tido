<?php

declare(strict_types=1);

use App\Filament\Widgets\MonthlyTrend;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('monthly trend widget renders with enriched chart data', function () {
    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 120.00,
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(MonthlyTrend::class)
        ->assertSuccessful()
        ->assertSee('Monthly Spending Trend (12 months to '.now()->format('M Y').')');
});
