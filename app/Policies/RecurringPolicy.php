<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Recurring;
use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Auth\Access\Response;

class RecurringPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recurring $recurring): bool
    {
        return true;
    }

    public function create(User $user): bool|Response
    {
        if ($user->isPrimary()) {
            return true;
        }

        return HouseholdAccess::createDeniedResponse();
    }

    public function replicate(User $user, Recurring $recurring): bool|Response
    {
        return $this->create($user);
    }

    public function update(User $user, Recurring $recurring): bool
    {
        return HouseholdAccess::canMutateRecurring($recurring);
    }

    public function delete(User $user, Recurring $recurring): bool
    {
        return HouseholdAccess::canMutateRecurring($recurring);
    }

    public function restore(User $user, Recurring $recurring): bool
    {
        return HouseholdAccess::canMutateRecurring($recurring);
    }

    public function forceDelete(User $user, Recurring $recurring): bool
    {
        return HouseholdAccess::canMutateRecurring($recurring);
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
