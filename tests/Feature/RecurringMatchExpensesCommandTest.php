<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    Expense::unsetEventDispatcher();
});

test('recurring match expenses command completes matches', function () {
    $recurring = Recurring::factory()->create([
        'title' => 'PTPTN',
        'merchant_aliases' => ['PTPTN'],
    ]);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-05',
        'status' => RecurringOccurrenceStatus::Overdue,
    ]);

    Expense::factory()->create([
        'merchant_name' => 'PTPTN',
        'total_amount' => 50,
        'date_time' => '2026-08-05 20:47:31',
        'status' => 'reviewed',
    ]);

    Artisan::call('recurring:match-expenses');

    expect(Artisan::output())->toContain('Completed 1 match(es)')
        ->and($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Completed)
        ->and((float) $occurrence->fresh()->actual_amount)->toBe(50.0);
});

test('recurring match expenses dry run previews without completing', function () {
    $recurring = Recurring::factory()->create([
        'title' => 'GPROP Monthly Bills',
        'merchant_aliases' => ['GPROP'],
    ]);

    $occurrence = RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'due_on' => '2026-08-05',
        'status' => RecurringOccurrenceStatus::Overdue,
    ]);

    Expense::factory()->create([
        'merchant_name' => 'GPROP Monthly Bills',
        'total_amount' => 199.14,
        'date_time' => '2026-08-05 20:53:20',
        'status' => 'reviewed',
    ]);

    Artisan::call('recurring:match-expenses', ['--dry-run' => true]);

    expect(Artisan::output())->toContain('Would complete 1 match(es)')
        ->and($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Overdue)
        ->and($occurrence->fresh()->expense_id)->toBeNull();
});
