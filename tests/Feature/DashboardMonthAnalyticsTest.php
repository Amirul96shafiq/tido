<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
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
    expect($trend['receipt_counts'])->toHaveCount(6);
    expect($trend['top_labels'])->toHaveCount(6);
    expect($trend['mom_changes'])->toHaveCount(6);
    expect($trend['period_shares'])->toHaveCount(6);
    expect($trend['selected_index'])->toBe(5);
    expect($trend['labels'][5])->toBe($targetMonth->format('m/y'));
    expect($trend['data'][5])->toBe(80.0);
    expect($trend['receipt_counts'][5])->toBe(1);
    expect($trend['mom_changes'][0])->toBeNull();
});

test('year to date trend ends at selected month', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->month(7)->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');

    Invoice::create([
        'merchant_name' => 'July Store',
        'invoice_number' => 'INV-JULY',
        'receipt_hash' => 'hash-july-001',
        'date_time' => $targetMonth->copy()->addDays(2),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $trend = analyticsForMonth($monthKey)->trend(yearToDate: true);

    expect($trend['labels'])->toHaveCount(7);
    expect($trend['labels'][0])->toBe($targetMonth->copy()->startOfYear()->format('m/y'));
    expect($trend['labels'][6])->toBe($targetMonth->format('m/y'));
    expect($trend['selected_index'])->toBe(6);
    expect($trend['data'][6])->toBe(90.0);
});

test('rolling twelve month trend ends at selected month', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->month(7)->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');
    $rangeStart = $targetMonth->copy()->subMonths(11);

    Invoice::create([
        'merchant_name' => 'Prior Year Store',
        'invoice_number' => 'INV-PRIOR-YEAR',
        'receipt_hash' => 'hash-prior-year-001',
        'date_time' => $rangeStart->copy()->addDays(2),
        'subtotal' => 40.00,
        'total_tax' => 0.00,
        'total_amount' => 40.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'July Store',
        'invoice_number' => 'INV-JULY-ROLLING',
        'receipt_hash' => 'hash-july-rolling-001',
        'date_time' => $targetMonth->copy()->addDays(2),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $trend = analyticsForMonth($monthKey)->trend(12);

    expect($trend['labels'])->toHaveCount(12);
    expect($trend['labels'][0])->toBe($rangeStart->format('m/y'));
    expect($trend['labels'][11])->toBe($targetMonth->format('m/y'));
    expect($trend['selected_index'])->toBe(11);
    expect($trend['data'][0])->toBe(40.0);
    expect($trend['data'][11])->toBe(90.0);
});

test('calendar year trend returns twelve buckets for selected year', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->month(7)->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');

    Invoice::create([
        'merchant_name' => 'July Store',
        'invoice_number' => 'INV-JULY',
        'receipt_hash' => 'hash-july-001',
        'date_time' => $targetMonth->copy()->addDays(2),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $trend = analyticsForMonth($monthKey)->trend(calendarYear: true);

    expect($trend['labels'])->toHaveCount(12);
    expect($trend['labels'][0])->toBe($targetMonth->copy()->startOfYear()->format('m/y'));
    expect($trend['labels'][11])->toBe($targetMonth->copy()->endOfYear()->format('m/y'));
    expect($trend['selected_index'])->toBe(6);
    expect($trend['data'][6])->toBe(90.0);
});

test('trend computes month over month change for consecutive months', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');
    $priorMonth = $targetMonth->copy()->subMonth();

    Invoice::create([
        'merchant_name' => 'Prior Month Store',
        'invoice_number' => 'INV-PRIOR',
        'receipt_hash' => 'hash-prior-001',
        'date_time' => $priorMonth->copy()->addDays(5),
        'subtotal' => 100.00,
        'total_tax' => 0.00,
        'total_amount' => 100.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Current Month Store',
        'invoice_number' => 'INV-CURRENT',
        'receipt_hash' => 'hash-current-001',
        'date_time' => $targetMonth->copy()->addDays(5),
        'subtotal' => 150.00,
        'total_tax' => 0.00,
        'total_amount' => 150.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $trend = analyticsForMonth($monthKey)->trend();
    $selectedIndex = $trend['selected_index'];

    expect($trend['mom_changes'][0])->toBeNull();
    expect($trend['mom_changes'][$selectedIndex])->toMatchArray([
        'delta' => 50.0,
        'percent' => 50.0,
    ]);
});

test('trend returns top three labels per month bucket', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');

    $groceries = Label::factory()->create(['name' => 'Groceries', 'slug' => 'groceries']);
    $transport = Label::factory()->create(['name' => 'Transport', 'slug' => 'transport']);
    $dining = Label::factory()->create(['name' => 'Dining', 'slug' => 'dining']);
    $misc = Label::factory()->create(['name' => 'Misc', 'slug' => 'misc']);

    $invoice = Invoice::create([
        'merchant_name' => 'Multi Label Store',
        'invoice_number' => 'INV-LABELS',
        'receipt_hash' => 'hash-labels-001',
        'date_time' => $targetMonth->copy()->addDays(3),
        'subtotal' => 200.00,
        'total_tax' => 0.00,
        'total_amount' => 200.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    foreach ([
        [$groceries, 80.00],
        [$transport, 60.00],
        [$dining, 40.00],
        [$misc, 20.00],
    ] as [$label, $amount]) {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'label_id' => $label->id,
            'description' => $label->name,
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);
    }

    Invoice::setEventDispatcher(app('events'));

    $trend = analyticsForMonth($monthKey)->trend();
    $selectedIndex = $trend['selected_index'];
    $topLabels = $trend['top_labels'][$selectedIndex];

    expect($topLabels)->toHaveCount(3);
    expect($topLabels[0])->toMatchArray(['name' => 'Groceries', 'total' => 80.0]);
    expect($topLabels[1])->toMatchArray(['name' => 'Transport', 'total' => 60.0]);
    expect($topLabels[2])->toMatchArray(['name' => 'Dining', 'total' => 40.0]);
});

test('trend period shares sum to one hundred percent when data present', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');
    $priorMonth = $targetMonth->copy()->subMonth();

    Invoice::create([
        'merchant_name' => 'Prior Month Store',
        'invoice_number' => 'INV-SHARE-PRIOR',
        'receipt_hash' => 'hash-share-prior',
        'date_time' => $priorMonth->copy()->addDays(2),
        'subtotal' => 75.00,
        'total_tax' => 0.00,
        'total_amount' => 75.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Current Month Store',
        'invoice_number' => 'INV-SHARE-CURRENT',
        'receipt_hash' => 'hash-share-current',
        'date_time' => $targetMonth->copy()->addDays(2),
        'subtotal' => 25.00,
        'total_tax' => 0.00,
        'total_amount' => 25.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $trend = analyticsForMonth($monthKey)->trend();
    $shareSum = array_sum($trend['period_shares']);

    expect($shareSum)->toBe(100.0);
    expect($trend['period_shares'][$trend['selected_index']])->toBe(25.0);
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
    expect($spending->first()->receipt_count)->toBe(1);
    expect($spending->first()->rank)->toBe(1);
    expect($spending->first()->label_count)->toBe(1);
    expect($spending->first()->mom_change)->toMatchArray([
        'delta' => 45.0,
        'percent' => null,
    ]);
    expect($spending->first()->top_merchant)->toMatchArray([
        'name' => 'Grocery Store',
        'total' => 45.0,
    ]);
});

test('spent by label excludes soft deleted invoices', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $label = Label::factory()->create([
        'name' => 'Dining',
        'slug' => 'dining',
    ]);

    $active = Invoice::create([
        'merchant_name' => 'Active Cafe',
        'invoice_number' => 'INV-ACTIVE',
        'receipt_hash' => 'hash-active-soft-001',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 20.00,
        'total_tax' => 0.00,
        'total_amount' => 20.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    $trashed = Invoice::create([
        'merchant_name' => 'Trashed Cafe',
        'invoice_number' => 'INV-TRASHED',
        'receipt_hash' => 'hash-trashed-soft-001',
        'date_time' => $bounds['start']->copy()->addDays(2),
        'subtotal' => 80.00,
        'total_tax' => 0.00,
        'total_amount' => 80.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    InvoiceItem::create([
        'invoice_id' => $active->id,
        'label_id' => $label->id,
        'description' => 'Coffee',
        'quantity' => 1,
        'unit_price' => 20.00,
        'line_total' => 20.00,
    ]);

    InvoiceItem::create([
        'invoice_id' => $trashed->id,
        'label_id' => $label->id,
        'description' => 'Lunch',
        'quantity' => 1,
        'unit_price' => 80.00,
        'line_total' => 80.00,
    ]);

    $trashed->delete();

    Invoice::setEventDispatcher(app('events'));

    $analytics = analyticsForMonth($targetMonth);
    $spending = $analytics->spentByLabel();
    $totals = $analytics->spentTotalsByLabelId();
    $trend = $analytics->trend();

    expect($spending)->toHaveCount(1);
    expect($spending->first()->total)->toBe(20.0);
    expect($spending->first()->receipt_count)->toBe(1);
    expect($totals[$label->id])->toBe(20.0);
    expect($totals[0])->toBe(20.0);
    expect($trend['data'][$trend['selected_index']])->toBe(20.0);
    expect($trend['top_labels'][$trend['selected_index']])->toMatchArray([
        ['name' => 'Dining', 'total' => 20.0],
    ]);
});

test('spent by label ranks higher totals first', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $groceries = Label::factory()->create(['name' => 'Groceries', 'slug' => 'groceries']);
    $transport = Label::factory()->create(['name' => 'Transport', 'slug' => 'transport']);

    foreach ([
        [$groceries, 30.00, 'Small Grocery'],
        [$transport, 90.00, 'Petrol Station'],
    ] as [$label, $amount, $merchant]) {
        $invoice = Invoice::create([
            'merchant_name' => $merchant,
            'invoice_number' => 'INV-'.strtoupper($label->slug),
            'receipt_hash' => 'hash-'.$label->slug,
            'date_time' => $bounds['start']->copy()->addDay(),
            'subtotal' => $amount,
            'total_tax' => 0.00,
            'total_amount' => $amount,
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'label_id' => $label->id,
            'description' => $label->name,
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);
    }

    Invoice::setEventDispatcher(app('events'));

    $spending = analyticsForMonth($targetMonth)->spentByLabel();

    expect($spending)->toHaveCount(2);
    expect($spending->first()->name)->toBe('Transport');
    expect($spending->first()->rank)->toBe(1);
    expect($spending->last()->name)->toBe('Groceries');
    expect($spending->last()->rank)->toBe(2);
    expect($spending->first()->label_count)->toBe(2);
});

test('spent by label counts one receipt with multiple line items', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $label = Label::factory()->create(['name' => 'Groceries', 'slug' => 'groceries']);

    $invoice = Invoice::create([
        'merchant_name' => 'Grocery Store',
        'invoice_number' => 'INV-MULTI',
        'receipt_hash' => 'hash-multi-001',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 70.00,
        'total_tax' => 0.00,
        'total_amount' => 70.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    foreach ([25.00, 45.00] as $amount) {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'label_id' => $label->id,
            'description' => 'Item',
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);
    }

    Invoice::setEventDispatcher(app('events'));

    $spending = analyticsForMonth($targetMonth)->spentByLabel();

    expect($spending->first()->receipt_count)->toBe(1);
    expect($spending->first()->total)->toBe(70.0);
});

test('spent by label computes month over month change', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->startOfMonth();
    $monthKey = $targetMonth->format('Y-m');
    $priorMonth = $targetMonth->copy()->subMonth();

    $label = Label::factory()->create(['name' => 'Groceries', 'slug' => 'groceries']);

    foreach ([
        [$priorMonth, 40.00, 'hash-prior-groc'],
        [$targetMonth, 100.00, 'hash-current-groc'],
    ] as [$month, $amount, $hash]) {
        $invoice = Invoice::create([
            'merchant_name' => 'Grocery Store',
            'invoice_number' => 'INV-'.$hash,
            'receipt_hash' => $hash,
            'date_time' => $month->copy()->addDays(3),
            'subtotal' => $amount,
            'total_tax' => 0.00,
            'total_amount' => $amount,
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'label_id' => $label->id,
            'description' => 'Groceries',
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);
    }

    Invoice::setEventDispatcher(app('events'));

    $spending = analyticsForMonth($monthKey)->spentByLabel();

    expect($spending->first()->mom_change)->toMatchArray([
        'delta' => 60.0,
        'percent' => 150.0,
    ]);
});

test('spent by label picks highest spend merchant for label', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $label = Label::factory()->create(['name' => 'Food & Dining', 'slug' => 'food-dining']);

    foreach ([
        ['Cafe A', 20.00, 'hash-cafe-a'],
        ['Restaurant B', 80.00, 'hash-restaurant-b'],
    ] as [$merchant, $amount, $hash]) {
        $invoice = Invoice::create([
            'merchant_name' => $merchant,
            'invoice_number' => 'INV-'.$hash,
            'receipt_hash' => $hash,
            'date_time' => $bounds['start']->copy()->addDay(),
            'subtotal' => $amount,
            'total_tax' => 0.00,
            'total_amount' => $amount,
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'label_id' => $label->id,
            'description' => 'Meal',
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);
    }

    Invoice::setEventDispatcher(app('events'));

    $spending = analyticsForMonth($targetMonth)->spentByLabel();

    expect($spending->first()->top_merchant)->toMatchArray([
        'name' => 'Restaurant B',
        'total' => 80.0,
    ]);
});

test('spent by label includes requires manual review invoices with labeled line items', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $label = Label::factory()->create([
        'name' => 'Pet Supplies',
        'slug' => 'pet-supplies',
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'Pet Lovers Centre',
        'invoice_number' => 'INV-PET',
        'receipt_hash' => 'hash-pet-manual-001',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 44.70,
        'total_tax' => 0.00,
        'discount_total' => 2.67,
        'total_amount' => 42.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'requires_manual_review',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Pet food',
        'quantity' => 1,
        'unit_price' => 19.86,
        'line_total' => 19.86,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Pet treats',
        'quantity' => 1,
        'unit_price' => 32.60,
        'line_total' => 32.60,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Promo discount',
        'quantity' => 1,
        'unit_price' => -8.80,
        'line_total' => -8.80,
    ]);

    Invoice::setEventDispatcher(app('events'));

    $spending = analyticsForMonth($targetMonth)->spentByLabel();

    expect($spending)->toHaveCount(1);
    expect($spending->first()->name)->toBe('Pet Supplies');
    expect($spending->first()->total)->toBe(43.66);
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

test('top merchants defaults to three results', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    foreach (['A' => 100, 'B' => 80, 'C' => 60, 'D' => 40] as $name => $amount) {
        Invoice::create([
            'merchant_name' => "Merchant {$name}",
            'invoice_number' => "INV-TOP-{$name}",
            'receipt_hash' => "hash-top-{$name}",
            'date_time' => $bounds['start']->copy()->addDay(),
            'subtotal' => $amount,
            'total_tax' => 0.00,
            'total_amount' => $amount,
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
        ]);
    }

    Invoice::setEventDispatcher(app('events'));

    $merchants = analyticsForMonth($targetMonth)->topMerchants();

    expect($merchants)->toHaveCount(3);
    expect($merchants->pluck('merchant_name')->all())->toBe([
        'Merchant A',
        'Merchant B',
        'Merchant C',
    ]);
});

test('spent by payment method groups spend, excludes pending, and computes mom', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    Invoice::create([
        'merchant_name' => 'Cash Store',
        'invoice_number' => 'INV-CASH-1',
        'receipt_hash' => 'hash-cash-1',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 80.00,
        'total_tax' => 0.00,
        'total_amount' => 80.00,
        'currency' => 'MYR',
        'payment_method' => PaymentMethod::Cash,
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Card Store',
        'invoice_number' => 'INV-VISA-1',
        'receipt_hash' => 'hash-visa-1',
        'date_time' => $bounds['start']->copy()->addDays(2),
        'subtotal' => 40.00,
        'total_tax' => 0.00,
        'total_amount' => 40.00,
        'currency' => 'MYR',
        'payment_method' => PaymentMethod::Visa,
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Pending Store',
        'invoice_number' => 'INV-PENDING-PM',
        'receipt_hash' => 'hash-pending-pm',
        'date_time' => $bounds['start']->copy()->addDays(3),
        'subtotal' => 99.00,
        'total_tax' => 0.00,
        'total_amount' => 99.00,
        'currency' => 'MYR',
        'payment_method' => PaymentMethod::Cash,
        'source' => 'manual',
        'status' => 'pending',
    ]);

    Invoice::create([
        'merchant_name' => 'Unknown Method Store',
        'invoice_number' => 'INV-UNKNOWN-PM',
        'receipt_hash' => 'hash-unknown-pm',
        'date_time' => $bounds['start']->copy()->addDays(4),
        'subtotal' => 20.00,
        'total_tax' => 0.00,
        'total_amount' => 20.00,
        'currency' => 'MYR',
        'payment_method' => null,
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Prior Cash Store',
        'invoice_number' => 'INV-CASH-PRIOR',
        'receipt_hash' => 'hash-cash-prior',
        'date_time' => $bounds['previous_start']->copy()->addDay(),
        'subtotal' => 30.00,
        'total_tax' => 0.00,
        'total_amount' => 30.00,
        'currency' => 'MYR',
        'payment_method' => PaymentMethod::Cash,
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $methods = analyticsForMonth($targetMonth)->spentByPaymentMethod();

    expect($methods)->toHaveCount(3);

    $cash = $methods->firstWhere('key', PaymentMethod::Cash->value);
    $visa = $methods->firstWhere('key', PaymentMethod::Visa->value);
    $unknown = $methods->firstWhere('key', '_unknown');

    expect($cash->label)->toBe('Cash')
        ->and($cash->total)->toBe(80.0)
        ->and($cash->receipt_count)->toBe(1)
        ->and($cash->spend_share_percent)->toEqualWithDelta(57.14, 0.01)
        ->and($cash->mom_change['delta'])->toBe(50.0)
        ->and($cash->mom_change['percent'])->toEqualWithDelta(166.67, 0.01);

    expect($visa->label)->toBe('Visa')
        ->and($visa->total)->toBe(40.0)
        ->and($visa->receipt_count)->toBe(1)
        ->and($visa->spend_share_percent)->toEqualWithDelta(28.57, 0.01);

    expect($unknown->label)->toBe('Unknown')
        ->and($unknown->total)->toBe(20.0)
        ->and($unknown->receipt_count)->toBe(1);
});

test('spent by payment method defaults to three results', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $methods = [
        PaymentMethod::Cash->value => 100,
        PaymentMethod::Visa->value => 80,
        PaymentMethod::Mastercard->value => 60,
        PaymentMethod::TouchNGo->value => 40,
    ];

    foreach ($methods as $method => $amount) {
        Invoice::create([
            'merchant_name' => "Store {$method}",
            'invoice_number' => "INV-PM-{$method}",
            'receipt_hash' => "hash-pm-{$method}",
            'date_time' => $bounds['start']->copy()->addDay(),
            'subtotal' => $amount,
            'total_tax' => 0.00,
            'total_amount' => $amount,
            'currency' => 'MYR',
            'payment_method' => $method,
            'source' => 'manual',
            'status' => 'reviewed',
        ]);
    }

    Invoice::setEventDispatcher(app('events'));

    $rows = analyticsForMonth($targetMonth)->spentByPaymentMethod();

    expect($rows)->toHaveCount(3);
    expect($rows->pluck('key')->all())->toBe([
        PaymentMethod::Cash->value,
        PaymentMethod::Visa->value,
        PaymentMethod::Mastercard->value,
    ]);
});

test('receipts by source groups counts, spend, and mom by upload channel', function () {
    Invoice::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    Invoice::create([
        'merchant_name' => 'Manual Store A',
        'invoice_number' => 'INV-MAN-1',
        'receipt_hash' => 'hash-man-1',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 50.00,
        'total_tax' => 0.00,
        'total_amount' => 50.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Manual Store B',
        'invoice_number' => 'INV-MAN-2',
        'receipt_hash' => 'hash-man-2',
        'date_time' => $bounds['start']->copy()->addDays(2),
        'subtotal' => 30.00,
        'total_tax' => 0.00,
        'total_amount' => 30.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'WhatsApp Store',
        'invoice_number' => 'INV-WA-1',
        'receipt_hash' => 'hash-wa-1',
        'date_time' => $bounds['start']->copy()->addDays(3),
        'subtotal' => 20.00,
        'total_tax' => 0.00,
        'total_amount' => 20.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'status' => 'parsed',
    ]);

    Invoice::create([
        'merchant_name' => 'Drive Store',
        'invoice_number' => 'INV-GD-1',
        'receipt_hash' => 'hash-gd-1',
        'date_time' => $bounds['start']->copy()->addDays(4),
        'subtotal' => 10.00,
        'total_tax' => 0.00,
        'total_amount' => 10.00,
        'currency' => 'MYR',
        'source' => 'google_drive',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Pending WhatsApp',
        'invoice_number' => 'INV-WA-PENDING',
        'receipt_hash' => 'hash-wa-pending',
        'date_time' => $bounds['start']->copy()->addDays(5),
        'subtotal' => 99.00,
        'total_tax' => 0.00,
        'total_amount' => 99.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'status' => 'pending',
    ]);

    Invoice::create([
        'merchant_name' => 'Prior Manual',
        'invoice_number' => 'INV-MAN-PRIOR',
        'receipt_hash' => 'hash-man-prior',
        'date_time' => $bounds['previous_start']->copy()->addDay(),
        'subtotal' => 15.00,
        'total_tax' => 0.00,
        'total_amount' => 15.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $sources = analyticsForMonth($targetMonth)->receiptsBySource();

    expect($sources)->toHaveCount(3);

    $manual = $sources->firstWhere('key', 'manual');
    $whatsapp = $sources->firstWhere('key', 'whatsapp');
    $drive = $sources->firstWhere('key', 'google_drive');

    expect($manual->label)->toBe('Manual')
        ->and($manual->receipt_count)->toBe(2)
        ->and($manual->total_spent)->toBe(80.0)
        ->and($manual->receipt_share_percent)->toBe(50.0)
        ->and($manual->mom_change['delta'])->toBe(1.0);

    expect($whatsapp->label)->toBe('WhatsApp')
        ->and($whatsapp->receipt_count)->toBe(1)
        ->and($whatsapp->total_spent)->toBe(20.0)
        ->and($whatsapp->receipt_share_percent)->toBe(25.0);

    expect($drive->label)->toBe('Google Drive')
        ->and($drive->receipt_count)->toBe(1)
        ->and($drive->total_spent)->toBe(10.0)
        ->and($drive->receipt_share_percent)->toBe(25.0);
});
