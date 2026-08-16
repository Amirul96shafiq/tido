<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Auth\Access\Response;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Budget $budget): bool
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

    public function replicate(User $user, Budget $budget): bool|Response
    {
        return $this->create($user);
    }

    public function update(User $user, Budget $budget): bool
    {
        return HouseholdAccess::canMutateBudget($budget);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return HouseholdAccess::canMutateBudget($budget);
    }

    public function restore(User $user, Budget $budget): bool
    {
        return HouseholdAccess::canMutateBudget($budget);
    }

    public function forceDelete(User $user, Budget $budget): bool
    {
        return HouseholdAccess::canMutateBudget($budget);
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
