<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Calendar\BirthdayCalendarProvider;
use App\Services\Calendar\CalendarEventAggregator;
use App\Services\Calendar\RecurringDueCalendarProvider;
use App\Support\Calendar\CalendarModule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('aggregator exposes filters from registered providers', function () {
    $aggregator = app(CalendarEventAggregator::class);

    expect($aggregator->availableFilters())->toBe([
        [
            'key' => 'recurring_dues',
            'label' => 'Recurring Dues',
            'module' => CalendarModule::Finances->value,
        ],
        [
            'key' => 'birthdays',
            'label' => 'Birthdays',
            'module' => CalendarModule::Household->value,
        ],
    ]);
});

test('aggregator merges events from all active filters', function () {
    $aggregator = new CalendarEventAggregator;
    $aggregator->register(new RecurringDueCalendarProvider);
    $aggregator->register(new BirthdayCalendarProvider);

    $user = User::factory()->create([
        'date_of_birth' => '1990-05-15',
    ]);

    $start = Carbon::create(1990, 5, 1)->startOfDay();
    $end = Carbon::create(1990, 5, 31)->endOfDay();

    $events = $aggregator->eventsForRange($start, $end, $user, ['birthdays']);

    expect($events)->toHaveCount(1)
        ->and($events->first()?->module)->toBe(CalendarModule::Household);
});

test('aggregator respects active filter keys', function () {
    $aggregator = app(CalendarEventAggregator::class);
    $user = User::factory()->create([
        'date_of_birth' => now()->format('Y-m-d'),
    ]);

    $start = now()->startOfMonth();
    $end = now()->endOfMonth();

    $events = $aggregator->eventsForRange($start, $end, $user, ['recurring_dues']);

    expect($events->every(fn ($event) => $event->module === CalendarModule::Finances))->toBeTrue();
});

test('aggregator shows no events when the active filter list is empty', function () {
    $aggregator = app(CalendarEventAggregator::class);
    $user = User::factory()->create([
        'date_of_birth' => now()->format('Y-m-d'),
    ]);

    $start = now()->startOfMonth();
    $end = now()->endOfMonth();

    expect($aggregator->eventsForRange($start, $end, $user, []))->toHaveCount(0);
});
