<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Support\CurrentHousehold;
use Closure;

final class SetCurrentHousehold
{
    public function handle(object $job, Closure $next): void
    {
        $previousHouseholdId = CurrentHousehold::id();
        CurrentHousehold::set($job->householdId);

        try {
            $next($job);
        } finally {
            if ($previousHouseholdId === null) {
                CurrentHousehold::clear();
            } else {
                CurrentHousehold::set($previousHouseholdId);
            }
        }
    }
}
