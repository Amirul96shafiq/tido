<?php

declare(strict_types=1);

use App\Models\Budget;

test('weekly budget period boundaries', function () {
    $budget = new Budget([
        'period' => 'weekly',
        'year' => 2026,
    ]);

    expect($budget->getStartDate()->toDateString())->toBe(now()->startOfWeek()->toDateString());
    expect($budget->getEndDate()->toDateString())->toBe(now()->endOfWeek()->toDateString());
});

test('monthly budget period boundaries', function () {
    $budget = new Budget([
        'period' => 'monthly',
        'year' => 2026,
    ]);

    expect($budget->getStartDate()->toDateString())->toBe(now()->startOfMonth()->toDateString());
    expect($budget->getEndDate()->toDateString())->toBe(now()->endOfMonth()->toDateString());
});

test('quarterly budget period boundaries', function () {
    $budget = new Budget([
        'period' => 'quarterly',
        'quarter' => 3,
        'year' => 2026,
    ]);

    expect($budget->getStartDate()->toDateString())->toBe('2026-07-01');
    expect($budget->getEndDate()->toDateString())->toBe('2026-09-30');
});

test('yearly budget period boundaries', function () {
    $budget = new Budget([
        'period' => 'yearly',
        'year' => 2026,
    ]);

    expect($budget->getStartDate()->toDateString())->toBe('2026-01-01');
    expect($budget->getEndDate()->toDateString())->toBe('2026-12-31');
});
