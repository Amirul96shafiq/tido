<?php

declare(strict_types=1);

use App\Support\Calendar\CalendarMonthPeriod;
use Carbon\Carbon;

test('calendar month period options span twelve months around reference', function () {
    $reference = Carbon::create(2026, 8, 1);

    $options = CalendarMonthPeriod::optionsAround($reference);

    expect($options)->toHaveCount(25)
        ->and(array_key_first($options))->toBe('2025-08')
        ->and(array_key_last($options))->toBe('2027-08')
        ->and($options['2026-08'])->toBe('August 2026');
});

test('calendar month period navigation window spans six months around reference', function () {
    $reference = Carbon::create(2026, 8, 1);

    $options = CalendarMonthPeriod::optionsAround(
        $reference,
        monthsBack: CalendarMonthPeriod::NAVIGATION_MONTHS_BACK,
        monthsForward: CalendarMonthPeriod::NAVIGATION_MONTHS_FORWARD,
    );

    expect($options)->toHaveCount(13)
        ->and(array_key_first($options))->toBe('2026-02')
        ->and(array_key_last($options))->toBe('2027-02')
        ->and($options['2026-08'])->toBe('August 2026');
});

test('calendar month period options clamp to navigation year bounds', function () {
    $reference = Carbon::create(CalendarMonthPeriod::navigationMinYear(), 1, 1);

    $options = CalendarMonthPeriod::optionsAround(
        $reference,
        minYear: CalendarMonthPeriod::navigationMinYear(),
        maxYear: CalendarMonthPeriod::navigationMaxYear(),
    );

    expect(array_key_first($options))->toBe(sprintf('%04d-01', CalendarMonthPeriod::navigationMinYear()))
        ->and(array_key_last($options))->toBe('2022-01');
});
