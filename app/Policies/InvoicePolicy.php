<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Support\HouseholdAccess;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return HouseholdAccess::canMutateInvoice($invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return HouseholdAccess::canMutateInvoice($invoice);
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return HouseholdAccess::canMutateInvoice($invoice);
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return HouseholdAccess::canMutateInvoice($invoice);
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
