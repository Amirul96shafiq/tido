<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HouseholdAccess;
use Filament\Facades\Filament;

trait RequiresPrimaryHouseholdAccess
{
    public static function canAccess(): bool
    {
        return HouseholdAccess::canManageHouseholdSettings();
    }

    public static function registerNavigationItems(): void
    {
        if (filled(static::getCluster())) {
            return;
        }

        if (! static::shouldRegisterNavigation()) {
            return;
        }

        $navigationItems = static::getNavigationItems();

        if (HouseholdAccess::isFamilyMember()) {
            foreach ($navigationItems as $navigationItem) {
                $navigationItem->extraAttributes([
                    'class' => 'tido-primary-only-navigation',
                    'x-data' => '{ tooltip: false }',
                    'x-tooltip' => '{ content: \''.HouseholdAccess::primaryOnlyAccessMessage().'\', theme: $store.theme }',
                ]);
            }
        }

        Filament::getCurrentOrDefaultPanel()->navigationItems($navigationItems);
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (property_exists(static::class, 'shouldRegisterNavigation') && static::$shouldRegisterNavigation === false) {
            return false;
        }

        return true;
    }
}
