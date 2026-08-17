<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled background tasks
Schedule::command('health:probe')->everyFifteenMinutes();
Schedule::command('currency:refresh-rates')
    ->dailyAt('00:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
Schedule::command('backup:clean')->daily()->at('03:00');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('health:prune')->daily()->at('04:00');
Schedule::command('recurring:generate-occurrences')
    ->dailyAt('00:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
Schedule::command('recurring:send-reminders')
    ->everyMinute()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
