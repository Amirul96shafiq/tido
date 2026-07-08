<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class DashboardMonthPeriod
{
    public static function fromFilters(?array $filters): Carbon
    {
        $month = $filters['month'] ?? now()->format('Y-m');

        return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    }

    public static function labelFromFilters(?array $filters): string
    {
        return self::fromFilters($filters)->format('F Y');
    }

    /**
     * @return array<string, string>
     */
    public static function options(int $monthsBack = 24): array
    {
        $options = [];

        for ($i = 0; $i < $monthsBack; $i++) {
            $date = now()->subMonths($i)->startOfMonth();
            $options[$date->format('Y-m')] = $date->format('F Y');
        }

        return $options;
    }

    public static function isCurrentMonth(CarbonInterface $month): bool
    {
        return $month->isSameMonth(now());
    }

    /**
     * @return array{start: Carbon, end: Carbon, previous_start: Carbon, previous_end: Carbon}
     */
    public static function boundsFromFilters(?array $filters): array
    {
        $selected = self::fromFilters($filters);
        $previous = $selected->copy()->subMonth();

        return [
            'start' => $selected->copy()->startOfMonth(),
            'end' => $selected->copy()->endOfMonth(),
            'previous_start' => $previous->copy()->startOfMonth(),
            'previous_end' => $previous->copy()->endOfMonth(),
        ];
    }
}
