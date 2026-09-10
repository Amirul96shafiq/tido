<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\HouseholdAccess;
use Filament\Actions\Action;

final class PrimaryOnlyMutationAuthorization
{
    public static function apply(Action $action): Action
    {
        return $action
            ->authorize(fn (): bool => HouseholdAccess::canManageHouseholdSettings())
            ->authorizationTooltip()
            ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage())
            ->extraAttributes(fn (): array => HouseholdAccess::isFamilyMember()
                ? ['class' => 'tido-primary-only-action']
                : []);
    }
}
