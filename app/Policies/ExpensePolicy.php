<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Support\HouseholdAccess;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Expense $expense): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Expense $expense): bool
    {
        return HouseholdAccess::canMutateExpense($expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return HouseholdAccess::canMutateExpense($expense);
    }

    public function restore(User $user, Expense $expense): bool
    {
        return HouseholdAccess::canMutateExpense($expense);
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        return HouseholdAccess::canMutateExpense($expense);
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
