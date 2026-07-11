<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Label;
use Database\Seeders\LabelSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('labels can be seeded and locked', function () {
    $this->seed(LabelSeeder::class);

    $this->assertDatabaseCount('labels', 9);
    $label = Label::where('slug', 'food-dining')->first();
    $this->assertNotNull($label);
    $this->assertTrue($label->is_system);
    expect($label->type->value)->toBe('finance');
});

test('manually created labels are not system locked', function () {
    $label = Label::factory()->create([
        'name' => 'Custom Category',
        'slug' => 'custom-category',
    ]);

    expect($label->is_system)->toBeFalse();
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

    $this->expectException(UniqueConstraintViolationException::class);

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
