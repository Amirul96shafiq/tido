<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

trait HasChartEmptyState
{
    abstract protected function isChartEmpty(): bool;

    protected function getEmptyStateHeading(): string
    {
        return 'No data';
    }

    protected function getEmptyStateDescription(): string
    {
        return 'Nothing recorded for this month.';
    }

    protected function getEmptyStateIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    protected function getEmptyStateIconColor(): string
    {
        return 'gray';
    }
}
