<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
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

    $expense = Expense::create([
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

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    // Force environment setting for WhatsApp number so WhatsApp notification dispatches
    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

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

    $expense = Expense::create([
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

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

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

    $expense = Expense::create([
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

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 96.00,
        'line_total' => 96.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

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

    $expense = Expense::create([
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

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

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

    $expense = Expense::create([
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

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/message/sendText/'));
    $this->assertDatabaseCount('notifications', 0);
});

test('budget alert service notifies only the primary admin even when other users opted in', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    $primary = User::factory()->create(['notify_budget_alerts' => true, 'phone' => '60123456789']);
    $other = User::factory()->create(['notify_budget_alerts' => true, 'phone' => '60111111111']);

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

    $expense = Expense::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-666',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

    expect($primary->fresh()->notifications()->count())->toBe(1)
        ->and($other->fresh()->notifications()->count())->toBe(0);
    $this->assertDatabaseCount('notifications', 1);
});

test('budget alert service does not re-alert the same budget level in the same period', function () {
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
        'is_active' => true,
    ]);

    $first = Expense::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-777',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    ExpenseItem::create([
        'expense_id' => $first->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $first->update(['status' => 'parsed']);

    $second = Expense::create([
        'merchant_name' => 'Starbucks',
        'invoice_number' => 'INV-778',
        'date_time' => now(),
        'subtotal' => 5.00,
        'total_tax' => 0.00,
        'total_amount' => 5.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
    ]);

    ExpenseItem::create([
        'expense_id' => $second->id,
        'label_id' => $label->id,
        'description' => 'Coffee',
        'quantity' => 1,
        'unit_price' => 5.00,
        'line_total' => 5.00,
    ]);

    $second->update(['status' => 'parsed']);

    $this->assertDatabaseCount('notifications', 1);
    Http::assertSentCount(1);
});

test('budget alert service ignores personal budgets for other household members', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    User::factory()->create(['phone' => '60123456789']);
    $member = FamilyMember::factory()->create();

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::factory()->forFamilyMember($member)->create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $expense = Expense::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-PERSONAL-SKIP',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'family_member_id' => null,
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

    Http::assertNothingSent();
    $this->assertDatabaseCount('notifications', 0);
});

test('budget alert service notifies assigned family member via whatsapp', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    User::factory()->create(['phone' => '60123456789']);
    $member = FamilyMember::factory()->create([
        'phone' => '60199887766',
    ]);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::factory()->forFamilyMember($member)->create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
        'notify_whatsapp' => true,
        'notify_filament' => true,
    ]);

    $expense = Expense::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-OWNER-WA',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'family_member_id' => $member->id,
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

    $sentNumbers = collect(Http::recorded())
        ->map(fn (array $pair): string => (string) ($pair[0]['number'] ?? ''))
        ->filter()
        ->values()
        ->all();

    expect($sentNumbers)->toContain('60123456789@s.whatsapp.net')
        ->and($sentNumbers)->toContain('60199887766@s.whatsapp.net');

    $this->assertDatabaseCount('notifications', 1);
});

test('shared budget still alerts when any household member spends', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);

    User::factory()->create(['phone' => '60123456789']);
    $member = FamilyMember::factory()->create();

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::factory()->shared()->create([
        'label_id' => $label->id,
        'family_member_id' => null,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $expense = Expense::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-SHARED',
        'date_time' => now(),
        'subtotal' => 90.00,
        'total_tax' => 0.00,
        'total_amount' => 90.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'pending',
        'family_member_id' => $member->id,
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    config([
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
    ]);

    $expense->update(['status' => 'parsed']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/message/sendText/'));
    $this->assertDatabaseCount('notifications', 1);
});
