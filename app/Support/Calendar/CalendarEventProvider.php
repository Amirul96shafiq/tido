<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CalendarEventProvider
{
    public function module(): CalendarModule;

    public function filterKey(): string;

    public function filterLabel(): string;

    /**
     * @return Collection<int, CalendarEvent>
     */
    public function eventsForRange(CarbonInterface $start, CarbonInterface $end, User $viewer): Collection;
}
