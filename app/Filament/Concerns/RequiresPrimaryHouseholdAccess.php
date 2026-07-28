<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HouseholdAccess;

trait RequiresPrimaryHouseholdAccess
{
    public static function canAccess(): bool
    {
        return HouseholdAccess::canManageHouseholdSettings();
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! HouseholdAccess::canManageHouseholdSettings()) {
            return false;
        }

        if (property_exists(static::class, 'shouldRegisterNavigation') && static::$shouldRegisterNavigation === false) {
            return false;
        }

        return true;
    }
}
