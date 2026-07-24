<?php

declare(strict_types=1);

use App\Filament\Support\DashboardMonthPeriod;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use App\Support\WhatsAppSpendingCommandParser;
use App\Support\WhatsAppSpendingReplyBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('summary includes total receipts comparison and footer', function () {
    Invoice::unsetEventDispatcher();

    Invoice::create([
        'merchant_name' => 'Store A',
        'invoice_number' => 'INV-001',
        'receipt_hash' => 'hash-spend-001',
        'date_time' => now()->copy()->startOfMonth()->addDay(),
        'subtotal' => 100.00,
        'total_tax' => 0.00,
        'total_amount' => 100.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::create([
        'merchant_name' => 'Store B',
        'invoice_number' => 'INV-002',
        'receipt_hash' => 'hash-spend-002',
        'date_time' => now()->copy()->subMonth()->startOfMonth()->addDay(),
        'subtotal' => 50.00,
        'total_tax' => 0.00,
        'total_amount' => 50.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Invoice::setEventDispatcher(app('events'));

    $message = (new WhatsAppSpendingReplyBuilder(now()->format('Y-m')))->build();

    expect($message)
        ->toContain('💰 *Monthly Spending*')
        ->toContain('Total spent: *RM 100.00*')
        ->toContain('Receipts: *1* processed')
        ->toContain('Forecast (end of month):')
        ->toContain('Top merchants:')
        ->toContain('*Store A*')
        ->toContain('— Powered by *tido*');
});

test('labels mode lists label spending for selected month', function () {
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
        'receipt_hash' => 'hash-groc-spend',
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

    $message = (new WhatsAppSpendingReplyBuilder(
        $targetMonth,
        WhatsAppSpendingCommandParser::MODE_LABELS,
    ))->build();

    expect($message)
        ->toContain('🏷️ *Spending by Label*')
        ->toContain('*Groceries* — RM 45.00');
});

test('budgets mode includes active budgets', function () {
    Budget::factory()->create([
        'title' => 'Food Budget',
        'amount' => 500.00,
        'period' => 'monthly',
        'is_active' => true,
    ]);

    $message = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_BUDGETS,
    ))->build();

    expect($message)
        ->toContain('📊 *Budget Status*')
        ->toContain('*Food Budget*');
});

test('summary reports empty month when no receipts exist', function () {
    $message = (new WhatsAppSpendingReplyBuilder('2020-01'))->build();

    expect($message)->toContain('No receipts recorded for *January 2020*');
});
