<?php

declare(strict_types=1);

use App\Filament\Widgets\MonthlyTrend;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('monthly trend widget renders with enriched chart data', function () {
    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'total_amount' => 120.00,
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(MonthlyTrend::class)
        ->assertSuccessful()
        ->assertSee('Monthly Spending Trend (12 months to '.now()->format('M Y').')')
        ->assertSee('data-chart-type="line"', false)
        ->assertSee('tension', false)
        ->assertSee('cubicInterpolationMode', false)
        ->assertSee('fill', false)
        ->assertSee("pointStyle: 'circle'", false)
        ->assertSee('pointStyleWidth: 14', false)
        ->assertSee('boxHeight: 10', false)
        ->assertSee('font: { size: 10 }', false)
        ->assertSee("'Top 3 Labels'", false);
});

test('monthly trend widget listens for echo expense updates without polling', function () {
    $component = Livewire::test(MonthlyTrend::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.30s')
        ->assertDontSeeHtml('wire:poll.5s');

    expect($component->instance()->getListeners())
        ->toHaveKey('echo-private:household.expenses,.ExpenseUpdated');
});
