<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HouseholdAccess;
use Filament\Facades\Filament;

trait RequiresPrimaryHouseholdAccess
{
    private const FAMILY_MEMBER_NAVIGATION_TOOLTIP = 'Only the Primary member can access this page.';

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
                    'x-tooltip' => '{ content: \''.self::FAMILY_MEMBER_NAVIGATION_TOOLTIP.'\', theme: $store.theme }',
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
