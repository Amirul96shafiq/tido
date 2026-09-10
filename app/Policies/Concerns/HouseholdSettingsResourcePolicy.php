<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

trait HouseholdSettingsResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $record): bool
    {
        return HouseholdAccess::belongsToSameHousehold($record->household_id);
    }

    public function create(User $user): bool|Response
    {
        if ($user->isPrimary()) {
            return true;
        }

        return HouseholdAccess::createDeniedResponse();
    }

    public function replicate(User $user, Model $record): bool|Response
    {
        return $this->create($user);
    }

    public function update(User $user, Model $record): bool|Response
    {
        if (! HouseholdAccess::belongsToSameHousehold($record->household_id)) {
            return false;
        }

        if ($user->isPrimary()) {
            return true;
        }

        return HouseholdAccess::createDeniedResponse();
    }

    public function delete(User $user, Model $record): bool|Response
    {
        return $this->update($user, $record);
    }

    public function restore(User $user, Model $record): bool|Response
    {
        return $this->update($user, $record);
    }

    public function forceDelete(User $user, Model $record): bool|Response
    {
        return $this->update($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function forceDeleteAny(User $user): bool
    {
        return true;
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }
}
