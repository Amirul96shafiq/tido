<?php

declare(strict_types=1);

use App\Filament\Widgets\MonthlySpendingOverview;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    Carbon::setTestNow();
});

test('spending forecast shows exceed percent above one hundred when barely over budget', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-21 12:00:00', 'Asia/Kuala_Lumpur'));

    Budget::factory()->create([
        'label_id' => null,
        'period' => 'monthly',
        'amount' => 1751.00,
        'is_active' => true,
        'year' => 2026,
    ]);

    Invoice::unsetEventDispatcher();

    // Month-to-date spend that projects to ~100.4% of budget (rounds to 100% with %.0f).
    Invoice::factory()->create([
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 1191.29,
        'total_tax' => 0,
        'total_amount' => 1191.29,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'forecast-exceed-barely'),
        'invoice_number' => 'INV-FORECAST-001',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $projectedSpend = 1191.29 + ((1191.29 / 21) * 10);
    $rawPercent = ($projectedSpend / 1751.00) * 100;

    expect($rawPercent)->toBeGreaterThan(100)
        ->and((int) round($rawPercent))->toBe(100);

    Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->assertSee('Projected to EXCEED budget (101%)')
        ->assertDontSee('Projected to EXCEED budget (100%)');
});

test('spending forecast shows large exceed percent without capping at one hundred', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-21 12:00:00', 'Asia/Kuala_Lumpur'));

    Budget::factory()->create([
        'label_id' => null,
        'period' => 'monthly',
        'amount' => 1000.00,
        'is_active' => true,
        'year' => 2026,
    ]);

    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 2000.00,
        'total_tax' => 0,
        'total_amount' => 2000.00,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'forecast-exceed-large'),
        'invoice_number' => 'INV-FORECAST-002',
    ]);

    Invoice::setEventDispatcher(app('events'));

    // 2000 + (2000/21)*10 = ~2952.38 → ~295% of 1000
    Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->assertSee('Projected to EXCEED budget (295%)')
        ->assertDontSee('Projected to EXCEED budget (100%)');
});
