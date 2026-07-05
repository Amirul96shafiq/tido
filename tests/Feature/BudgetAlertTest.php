<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

    $user = User::factory()->create();

    $category = Category::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    $budget = Budget::create([
        'category_id' => $category->id,
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
        'category_id' => $category->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    // Force environment setting for WhatsApp number so WhatsApp notification dispatches
    config([
        'services.evolution.api_key' => 'tido-secret-key',
        'services.evolution.personal_number' => '60123456789',
    ]);

    $invoice->update(['status' => 'parsed']);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/message/sendText/')
            && str_contains((string) $request['text'], 'Budget Alert: Food & Dining')
            && str_contains((string) $request['text'], 'RM 90.00 / RM 100.00');
    });

    $this->assertDatabaseCount('notifications', 1);
});
