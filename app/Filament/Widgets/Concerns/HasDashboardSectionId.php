<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

trait HasDashboardSectionId
{
    abstract public static function dashboardSectionId(): string;

    public function getDashboardSectionId(): string
    {
        return static::dashboardSectionId();
    }
}
