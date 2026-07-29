<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

trait HasDashboardChartPolling
{
    protected function getPollingInterval(): ?string
    {
        if (method_exists($this, 'isCurrentMonthSelected') && ! $this->isCurrentMonthSelected()) {
            return null;
        }

        return '30s';
    }
}
