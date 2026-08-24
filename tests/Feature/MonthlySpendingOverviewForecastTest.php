<?php

declare(strict_types=1);

use App\Filament\Widgets\MonthlySpendingOverview;
use App\Models\Budget;
use App\Models\Expense;
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

    Expense::unsetEventDispatcher();

    // Month-to-date spend that projects to ~100.4% of budget (rounds to 100% with %.0f).
    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 1191.29,
        'total_tax' => 0,
        'total_amount' => 1191.29,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'forecast-exceed-barely'),
        'invoice_number' => 'INV-FORECAST-001',
    ]);

    Expense::setEventDispatcher(app('events'));

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

    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 2000.00,
        'total_tax' => 0,
        'total_amount' => 2000.00,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'forecast-exceed-large'),
        'invoice_number' => 'INV-FORECAST-002',
    ]);

    Expense::setEventDispatcher(app('events'));

    // 2000 + (2000/21)*10 = ~2952.38 → ~295% of 1000
    Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->assertSee('Projected to EXCEED budget (295%)')
        ->assertDontSee('Projected to EXCEED budget (100%)');
});

test('monthly spending overview stat descriptions use single-line marquee markup', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', 'Asia/Kuala_Lumpur'));

    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 50.00,
        'total_tax' => 0,
        'total_amount' => 50.00,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'overview-marquee-july'),
        'invoice_number' => 'INV-MARQUEE-JULY',
    ]);

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-08-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 120.00,
        'total_tax' => 7.20,
        'total_amount' => 127.20,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'overview-marquee-august'),
        'invoice_number' => 'INV-MARQUEE-AUG',
    ]);

    Expense::setEventDispatcher(app('events'));

    $html = Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->html();

    expect(substr_count($html, 'tido-text-marquee-clip'))->toBeGreaterThanOrEqual(4)
        ->and(substr_count($html, 'x-ref="marqueeSegment"'))->toBeGreaterThanOrEqual(4)
        ->and(substr_count($html, 'wire:ignore'))->toBeGreaterThanOrEqual(4)
        ->and($html)->toContain('tido-text-marquee-track')
        ->and($html)->toContain('whitespace-nowrap')
        ->and($html)->toContain('x-ref="marqueeTrack"');
});

test('monthly spending overview renders count-up stats without losing formatted values', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', 'Asia/Kuala_Lumpur'));

    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-08-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 120.00,
        'total_tax' => 7.20,
        'total_amount' => 127.20,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'overview-count-up'),
        'invoice_number' => 'INV-COUNT-UP',
    ]);

    Expense::setEventDispatcher(app('events'));

    $html = Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->html();

    expect(substr_count($html, 'fi-count-up'))->toBeGreaterThanOrEqual(4)
        ->and(substr_count($html, 'x-data="countUp('))->toBeGreaterThanOrEqual(4)
        ->and($html)->toContain('RM 127.20');
});

test('monthly spending overview renders a native sparkline for every stat', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-21 12:00:00', 'Asia/Kuala_Lumpur'));

    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-06-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 80.00,
        'total_tax' => 4.00,
        'total_amount' => 84.00,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'overview-sparkline-prior'),
        'invoice_number' => 'INV-SPARKLINE-001',
    ]);

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 120.00,
        'total_tax' => 6.00,
        'total_amount' => 126.00,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'overview-sparkline-current'),
        'invoice_number' => 'INV-SPARKLINE-002',
    ]);

    Expense::setEventDispatcher(app('events'));

    $html = Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->html();

    expect(substr_count($html, 'x-data="statsOverviewStatChart('))->toBe(4)
        ->and(substr_count($html, '<canvas x-ref="canvas" aria-hidden="true"></canvas>'))->toBe(4);
});

test('monthly spending overview uses half-width desktop and a responsive two-column grid', function () {
    $widget = Livewire::test(MonthlySpendingOverview::class)->instance();
    $columns = (new ReflectionProperty($widget, 'columns'))->getValue($widget);

    expect($widget->getColumnSpan())->toBe([
        'default' => 'full',
        'xl' => 6,
    ])->and($columns)->toBe([
        'default' => 1,
        'md' => 2,
    ]);
});

test('receipts processed stat shows selected month title and month over month comparison', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', 'Asia/Kuala_Lumpur'));

    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 50.00,
        'total_tax' => 0,
        'total_amount' => 50.00,
        'status' => 'reviewed',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'receipts-overview-july'),
        'invoice_number' => 'INV-RECEIPTS-JULY',
    ]);

    foreach (['001', '002', '003'] as $suffix) {
        Expense::factory()->create([
            'date_time' => Carbon::parse('2026-08-10 10:00:00', 'Asia/Kuala_Lumpur'),
            'subtotal' => 30.00,
            'total_tax' => 0,
            'total_amount' => 30.00,
            'status' => 'reviewed',
            'source' => 'manual',
            'receipt_hash' => hash('sha256', 'receipts-overview-august-'.$suffix),
            'invoice_number' => 'INV-RECEIPTS-AUG-'.$suffix,
        ]);
    }

    Expense::factory()->create([
        'date_time' => Carbon::parse('2026-08-12 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 20.00,
        'total_tax' => 0,
        'total_amount' => 20.00,
        'status' => 'pending',
        'source' => 'manual',
        'receipt_hash' => hash('sha256', 'receipts-overview-pending'),
        'invoice_number' => 'INV-RECEIPTS-PENDING',
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->assertSee('Receipts Processed (August 2026)')
        ->assertSee('RM 40.00 (+80.0%) total spent vs July 2026')
        ->assertSee('2 (+200.0%) receipts processed vs July 2026')
        ->assertDontSee('pending parsing');
});

test('monthly spending overview listens for echo expense updates without polling', function () {
    $component = Livewire::test(MonthlySpendingOverview::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.30s');

    expect($component->instance()->getListeners())
        ->toHaveKey('echo-private:household.expenses,.ExpenseUpdated');
});
