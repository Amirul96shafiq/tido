<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\PrimaryOnlyMutationAuthorization;
use App\Support\HouseholdAccess;
use Filament\Actions\Action;

trait EnsuresPrimaryHouseholdMutation
{
    protected function ensurePrimaryHouseholdMutation(): void
    {
        HouseholdAccess::ensureCanManageHouseholdSettings();
    }

    protected function primaryOnlyAction(Action $action): Action
    {
        return PrimaryOnlyMutationAuthorization::apply($action);
    }
}
