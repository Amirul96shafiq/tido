<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Labeling;
use Database\Seeders\LabelingSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('labelings can be seeded and locked', function () {
    $this->seed(LabelingSeeder::class);

    $this->assertDatabaseCount('labelings', 9);
    $labeling = Labeling::where('slug', 'food-dining')->first();
    $this->assertNotNull($labeling);
    $this->assertTrue($labeling->is_system);
    expect($labeling->type->value)->toBe('finance');
});

test('manually created labelings are not system locked', function () {
    $labeling = Labeling::factory()->create([
        'name' => 'Custom Category',
        'slug' => 'custom-category',
    ]);

    expect($labeling->is_system)->toBeFalse();
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
