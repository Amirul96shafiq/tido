<?php

declare(strict_types=1);

use App\Jobs\SyncGoogleDriveJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled background tasks
Schedule::job(new SyncGoogleDriveJob)->everyFifteenMinutes();
Schedule::command('backup:clean')->daily()->at('03:00');
Schedule::command('backup:run')->daily()->at('02:00');
