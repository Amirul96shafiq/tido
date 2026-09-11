<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

trait RequiresPrimaryHouseholdAccess
{
    public static function canAccess(): bool
    {
        return true;
    }
}
