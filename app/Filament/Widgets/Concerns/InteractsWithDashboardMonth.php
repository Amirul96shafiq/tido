<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Filament\Support\DashboardMonthPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait InteractsWithDashboardMonth
{
    use InteractsWithPageFilters;

    protected function getSelectedMonth(): Carbon
    {
        return DashboardMonthPeriod::fromFilters($this->pageFilters);
    }

    /**
     * @return array{start: Carbon, end: Carbon, previous_start: Carbon, previous_end: Carbon}
     */
    protected function getSelectedMonthBounds(): array
    {
        return DashboardMonthPeriod::boundsFromFilters($this->pageFilters);
    }

    protected function isCurrentMonthSelected(): bool
    {
        return DashboardMonthPeriod::isCurrentMonth($this->getSelectedMonth());
    }

    protected function formatSelectedMonth(string $format = 'M Y'): string
    {
        return $this->getSelectedMonth()->format($format);
    }

    protected function getMonthReferenceDate(): CarbonInterface
    {
        return $this->getSelectedMonth()->copy()->startOfMonth();
    }
}
