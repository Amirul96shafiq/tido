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

    public function view(User $user, Expense $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Expense $invoice): bool
    {
        return HouseholdAccess::canMutateExpense($invoice);
    }

    public function delete(User $user, Expense $invoice): bool
    {
        return HouseholdAccess::canMutateExpense($invoice);
    }

    public function restore(User $user, Expense $invoice): bool
    {
        return HouseholdAccess::canMutateExpense($invoice);
    }

    public function forceDelete(User $user, Expense $invoice): bool
    {
        return HouseholdAccess::canMutateExpense($invoice);
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
