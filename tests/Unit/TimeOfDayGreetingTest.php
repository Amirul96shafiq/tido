<?php

declare(strict_types=1);

use App\Enums\TimeOfDayPeriod;
use App\Support\TimeOfDayGreeting;
use Carbon\Carbon;

test('resolves morning period between 05:00 and 11:59', function (string $time) {
    $now = Carbon::parse($time, 'Asia/Kuala_Lumpur');

    expect(TimeOfDayGreeting::period($now))->toBe(TimeOfDayPeriod::Morning)
        ->and(TimeOfDayGreeting::emoji($now))->toBe('☀️')
        ->and(TimeOfDayGreeting::greeting($now))->toBe('Good Morning')
        ->and(TimeOfDayGreeting::subheading($now))
        ->toBe('Ready to start the day? Start by tidying up your files, then get it done.');
})->with([
    '05:00' => ['2026-07-16 05:00:00'],
    '11:59' => ['2026-07-16 11:59:59'],
]);

test('resolves afternoon period between 12:00 and 17:59', function (string $time) {
    $now = Carbon::parse($time, 'Asia/Kuala_Lumpur');

    expect(TimeOfDayGreeting::period($now))->toBe(TimeOfDayPeriod::Afternoon)
        ->and(TimeOfDayGreeting::emoji($now))->toBe('🌤️')
        ->and(TimeOfDayGreeting::greeting($now))->toBe('Good Afternoon')
        ->and(TimeOfDayGreeting::subheading($now))
        ->toBe('Ready to keep going? Start by tidying up your files, then get it done.');
})->with([
    '12:00' => ['2026-07-16 12:00:00'],
    '17:59' => ['2026-07-16 17:59:59'],
]);

test('resolves evening period outside morning and afternoon hours', function (string $time) {
    $now = Carbon::parse($time, 'Asia/Kuala_Lumpur');

    expect(TimeOfDayGreeting::period($now))->toBe(TimeOfDayPeriod::Evening)
        ->and(TimeOfDayGreeting::emoji($now))->toBe('🌙')
        ->and(TimeOfDayGreeting::greeting($now))->toBe('Good Evening')
        ->and(TimeOfDayGreeting::subheading($now))
        ->toBe('Ready to wrap up? Start by tidying up your files, then get it done.');
})->with([
    '00:00' => ['2026-07-16 00:00:00'],
    '04:59' => ['2026-07-16 04:59:59'],
    '18:00' => ['2026-07-16 18:00:00'],
    '23:59' => ['2026-07-16 23:59:59'],
]);

test('heading places emoji after greeting and shortened name', function () {
    $now = Carbon::parse('2026-07-16 08:30:00', 'Asia/Kuala_Lumpur');

    expect(TimeOfDayGreeting::headingFor($now, 'Ada'))
        ->toBe('Good Morning, Ada ☀️');
});

test('heading html highlights shortened name with primary color', function () {
    $now = Carbon::parse('2026-07-16 08:30:00', 'Asia/Kuala_Lumpur');

    expect((string) TimeOfDayGreeting::headingHtmlFor($now, 'Amirul Shafiq Harun'))
        ->toContain('Good Morning, <span class="text-primary-600 dark:text-primary-400">Amirul S. H.</span> ☀️');
});

test('shortens multi-part names to first name plus initials', function () {
    expect(TimeOfDayGreeting::shortenName('Amirul Shafiq Harun'))
        ->toBe('Amirul S. H.')
        ->and(TimeOfDayGreeting::shortenName('Budi Santoso'))
        ->toBe('Budi S.')
        ->and(TimeOfDayGreeting::shortenName('Ada'))
        ->toBe('Ada');
});
