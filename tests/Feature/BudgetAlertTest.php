<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('budget alert service triggers alerts on threshold breach', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $user = User::factory()->create(['phone' => '60123456789']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    $budget = Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-111',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    // Force environment setting for WhatsApp number so WhatsApp notification dispatches
    config([
        'services.evolution.api_key' => 'tido-secret-key',
    ]);

    $invoice->update(['status' => 'parsed']);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], 'Budget alert')
            && str_contains((string) $request['text'], 'Food & Dining')
            && str_contains((string) $request['text'], 'RM 90.00')
            && str_contains((string) $request['text'], 'RM 100.00')
            && str_contains((string) $request['text'], '— Powered by *tido*');
    });

    $this->assertDatabaseCount('notifications', 1);
});

test('budget alert service skips users who opted out of budget alerts', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    User::factory()->create(['notify_budget_alerts' => true, 'phone' => '60123456789']);
    User::factory()->create(['notify_budget_alerts' => false, 'phone' => '60111111111']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-222',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'tido-secret-key',
    ]);

    $invoice->update(['status' => 'parsed']);

    $this->assertDatabaseCount('notifications', 1);
});

test('budget alert service sends critical notification at critical threshold', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    User::factory()->create(['phone' => '60123456789']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'critical_threshold' => 95,
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-333',
        'date_time' => now(),
        'subtotal' => 96.00,
        'total_tax' => 0.00,
        'total_amount' => 96.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 96.00,
        'line_total' => 96.00,
    ]);

    config([
        'services.evolution.api_key' => 'tido-secret-key',
    ]);

    $invoice->update(['status' => 'parsed']);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], 'Budget critical')
            && str_contains((string) $request['text'], 'critical threshold');
    });

    $this->assertDatabaseCount('notifications', 1);
});

test('budget alert service skips whatsapp when notify_whatsapp is false', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    User::factory()->create(['phone' => '60123456789']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'critical_threshold' => 100,
        'notify_filament' => true,
        'notify_whatsapp' => false,
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-444',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'tido-secret-key',
    ]);

    $invoice->update(['status' => 'parsed']);

    Http::assertNothingSent();
    $this->assertDatabaseCount('notifications', 1);
});

test('budget alert service skips filament when notify_filament is false', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    User::factory()->create(['phone' => '60123456789']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'critical_threshold' => 100,
        'notify_filament' => false,
        'notify_whatsapp' => true,
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-555',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'tido-secret-key',
    ]);

    $invoice->update(['status' => 'parsed']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/message/sendText/'));
    $this->assertDatabaseCount('notifications', 0);
});
