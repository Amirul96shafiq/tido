<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class CalendarMonthPeriod
{
    /**
     * @return array<string, string>
     */
    public static function optionsAround(
        CarbonInterface $reference,
        int $monthsBack = 12,
        int $monthsForward = 12,
        ?int $minYear = null,
        ?int $maxYear = null,
    ): array {
        $reference = Carbon::parse($reference)->startOfMonth();
        $start = $reference->copy()->subMonths($monthsBack);
        $end = $reference->copy()->addMonths($monthsForward);

        if ($minYear !== null) {
            $minStart = Carbon::create($minYear, 1, 1)->startOfMonth();

            if ($start->lessThan($minStart)) {
                $start = $minStart;
            }
        }

        if ($maxYear !== null) {
            $maxEnd = Carbon::create($maxYear, 12, 1)->startOfMonth();

            if ($end->greaterThan($maxEnd)) {
                $end = $maxEnd;
            }
        }

        $options = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $options[$cursor->format('Y-m')] = $cursor->format('F Y');
            $cursor->addMonth();
        }

        return $options;
    }

    public static function navigationMinYear(): int
    {
        return now()->year - 5;
    }

    public static function navigationMaxYear(): int
    {
        return now()->year + 5;
    }
}
