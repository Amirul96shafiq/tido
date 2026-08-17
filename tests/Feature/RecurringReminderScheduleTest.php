<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

test('recurring send reminders is scheduled every minute in the app timezone', function () {
    $event = collect(Schedule::events())
        ->first(fn ($scheduled): bool => str_contains($scheduled->command ?? '', 'recurring:send-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->timezone)->toBe(config('app.timezone'));
});
