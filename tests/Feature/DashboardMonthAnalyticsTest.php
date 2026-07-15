<?php

declare(strict_types=1);

use App\Filament\Support\DashboardMonthAnalytics;
use App\Filament\Support\DashboardMonthPeriod;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function analyticsForMonth(string $month): DashboardMonthAnalytics
{
    return new DashboardMonthAnalytics(
        DashboardMonthPeriod::boundsFromFilters(['month' => $month]),
    );
}

test('summary aggregates respect month bounds and processed status filter', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    Invoice::create([
        'merchant_name' => 'Processed Store',
        'invoice_number' => 'INV-001',
        'receipt_hash' => 'hash-processed-001',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 100.00,
        'total_tax' => 6.00,
        'total_amount' => 106.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Pending Store',
        'invoice_number' => 'INV-002',
        'receipt_hash' => 'hash-pending-002',
        'date_time' => $bounds['start']->copy()->addDays(2),
        'subtotal' => 50.00,
        'total_tax' => 3.00,
        'total_amount' => 53.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    Invoice::create([
        'merchant_name' => 'Previous Month Store',
        'invoice_number' => 'INV-003',
        'receipt_hash' => 'hash-previous-003',
        'date_time' => $bounds['previous_start']->copy()->addDay(),
        'subtotal' => 200.00,
        'total_tax' => 12.00,
        'total_amount' => 212.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $summary = analyticsForMonth($targetMonth)->summary();

    expect($summary['current_total'])->toBe(106.0);
    expect($summary['previous_total'])->toBe(212.0);
    expect($summary['current_tax'])->toBe(6.0);
    expect($summary['pending_count'])->toBe(1);
    expect($summary['processed_count'])->toBe(1);
});

test('trend returns six buckets ending at selected month', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonths(2)->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');

    Invoice::create([
        'merchant_name' => 'Trend Store',
        'invoice_number' => 'INV-TREND',
        'receipt_hash' => 'hash-trend-001',
        'date_time' => $targetMonth->copy()->addDays(3),
        'subtotal' => 80.00,
        'total_tax' => 0.00,
        'total_amount' => 80.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'parsed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $trend = analyticsForMonth($monthKey)->trend();

    expect($trend['labels'])->toHaveCount(6);
    expect($trend['data'])->toHaveCount(6);
    expect($trend['selected_index'])->toBe(5);
    expect($trend['labels'][5])->toBe($targetMonth->format('M Y'));
    expect($trend['data'][5])->toBe(80.0);
});

test('spent by label sums line items for selected month', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $label = Label::factory()->create([
        'name' => 'Groceries',
        'slug' => 'groceries',
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'Grocery Store',
        'invoice_number' => 'INV-GROC',
        'receipt_hash' => 'hash-groc-001',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 45.00,
        'total_tax' => 0.00,
        'total_amount' => 45.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Vegetables',
        'quantity' => 1,
        'unit_price' => 45.00,
        'line_total' => 45.00,
    ]);

    Invoice::setEventDispatcher(app('events'));

    $spending = analyticsForMonth($targetMonth)->spentByLabel();

    expect($spending)->toHaveCount(1);
    expect($spending->first()->name)->toBe('Groceries');
    expect($spending->first()->total)->toBe(45.0);
});

test('budget mapping uses calendar month bounds for weekly budgets', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $monthKey]);

    $label = Label::factory()->create([
        'name' => 'Transport',
        'slug' => 'transport',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 500.00,
        'period' => 'weekly',
        'year' => (int) $targetMonth->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'Petrol Station',
        'invoice_number' => 'INV-PETROL',
        'receipt_hash' => 'hash-petrol-001',
        'date_time' => $bounds['start']->copy()->addDays(10),
        'subtotal' => 120.00,
        'total_tax' => 0.00,
        'total_amount' => 120.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Fuel',
        'quantity' => 1,
        'unit_price' => 120.00,
        'line_total' => 120.00,
    ]);

    Invoice::setEventDispatcher(app('events'));

    $totals = analyticsForMonth($monthKey)->spentTotalsByLabelId();

    expect($totals[$label->id])->toBe(120.0);
});

test('top merchants aggregates spent, discount, receipts, and spend share', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    Invoice::create([
        'merchant_name' => 'Merchant A',
        'invoice_number' => 'INV-A1',
        'receipt_hash' => 'hash-a1',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 50.00,
        'total_tax' => 0.00,
        'discount_total' => 5.00,
        'total_amount' => 50.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Merchant A',
        'invoice_number' => 'INV-A2',
        'receipt_hash' => 'hash-a2',
        'date_time' => $bounds['start']->copy()->addDays(2),
        'subtotal' => 25.00,
        'total_tax' => 0.00,
        'discount_total' => 2.50,
        'total_amount' => 25.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Merchant B',
        'invoice_number' => 'INV-B1',
        'receipt_hash' => 'hash-b1',
        'date_time' => $bounds['start']->copy()->addDays(3),
        'subtotal' => 25.00,
        'total_tax' => 0.00,
        'discount_total' => 0.00,
        'total_amount' => 25.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $merchants = analyticsForMonth($targetMonth)->topMerchants();

    expect($merchants)->toHaveCount(2);

    $merchantA = $merchants->firstWhere('merchant_name', 'Merchant A');
    $merchantB = $merchants->firstWhere('merchant_name', 'Merchant B');

    expect($merchantA->total_spent)->toBe(75.0);
    expect($merchantA->total_discount)->toBe(7.5);
    expect($merchantA->receipt_count)->toBe(2);
    expect($merchantA->avg_spend)->toBe(37.5);
    expect($merchantA->spend_share_percent)->toBe(75.0);

    expect($merchantB->total_spent)->toBe(25.0);
    expect($merchantB->total_discount)->toBe(0.0);
    expect($merchantB->receipt_count)->toBe(1);
    expect($merchantB->avg_spend)->toBe(25.0);
    expect($merchantB->spend_share_percent)->toBe(25.0);
});
