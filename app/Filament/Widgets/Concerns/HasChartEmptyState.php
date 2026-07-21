<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;

trait HasChartEmptyState
{
    abstract protected function isChartEmpty(): bool;

    public function isEmpty(): bool
    {
        return $this->isChartEmpty();
    }

    public function getEmptyStateHeading(): string|Htmlable
    {
        return 'No data';
    }

    public function getEmptyStateDescription(): string|Htmlable|null
    {
        return 'Nothing recorded for this month.';
    }

    public function getEmptyStateIcon(): string|BackedEnum|Htmlable
    {
        return 'heroicon-o-chart-bar';
    }

    protected function getEmptyStateIconColor(): string
    {
        return 'gray';
    }
}
