<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('categories can be seeded and locked', function () {
    $this->seed(\Database\Seeders\CategorySeeder::class);

    $this->assertDatabaseCount('categories', 9);
    $category = Category::where('slug', 'food-dining')->first();
    $this->assertNotNull($category);
    $this->assertTrue($category->is_system);
});

test('invoices generate hash on creation and block duplicates', function () {
    $invoice = Invoice::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-001',
        'date_time' => now(),
        'subtotal' => 10.00,
        'total_tax' => 0.60,
        'total_amount' => 10.60,
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    $this->assertNotEmpty($invoice->receipt_hash);

    // Expect exception on duplicate hash
    $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
    
    Invoice::create([
        'merchant_name' => 'McDonalds',
        'invoice_number' => 'INV-001',
        'date_time' => $invoice->date_time,
        'subtotal' => 10.00,
        'total_tax' => 0.60,
        'total_amount' => 10.60,
        'source' => 'manual',
        'status' => 'reviewed',
    ]);
});

test('budgets support custom periods', function () {
    $budgetDaily = Budget::factory()->create([
        'period' => 'daily',
        'year' => 2026,
    ]);
    expect($budgetDaily->getStartDate()->toDateString())->toBe(now()->startOfDay()->toDateString());
    expect($budgetDaily->getEndDate()->toDateString())->toBe(now()->endOfDay()->toDateString());

    $budgetQuarterly = Budget::factory()->create([
        'period' => 'quarterly',
        'quarter' => 2,
        'year' => 2026,
    ]);
    expect($budgetQuarterly->getStartDate()->toDateString())->toBe('2026-04-01');
    expect($budgetQuarterly->getEndDate()->toDateString())->toBe('2026-06-30');
});
