<?php

declare(strict_types=1);

use App\Enums\UserDateFormat;
use Carbon\Carbon;

test('user date format menu calendar pill formats', function () {
    $date = Carbon::create(2026, 8, 14, 12, 0, 0, 'Asia/Kuala_Lumpur');

    expect($date->format(UserDateFormat::DmySlash->menuCalendarPillFormat()))->toBe('14/8')
        ->and($date->format(UserDateFormat::DmyLong->menuCalendarPillFormat()))->toBe('14 Aug')
        ->and($date->format(UserDateFormat::Iso->menuCalendarPillFormat()))->toBe('08-14');
});
