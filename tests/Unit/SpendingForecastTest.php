<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Services\SpendingForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('forecast monthly spend calculates projections', function () {
    Expense::unsetEventDispatcher();

    for ($i = 10; $i >= 1; $i--) {
        Expense::create([
            'merchant_name' => 'Store',
            'invoice_number' => 'INV-'.$i,
            'date_time' => now()->subDays($i),
            'subtotal' => 10.00 * (11 - $i),
            'total_tax' => 0.00,
            'total_amount' => 10.00 * (11 - $i),
            'currency' => 'MYR',
            'source' => 'manual',
            'status' => 'reviewed',
            'receipt_hash' => 'HASH_'.$i,
        ]);
    }

    Expense::setEventDispatcher(app('events'));

    $service = new SpendingForecastService;
    $result = $service->forecastMonthlySpend();

    expect($result)->toHaveKey('forecast');
    expect($result)->toHaveKey('confidence');
    expect($result['forecast'])->toBeGreaterThan(0);
});
