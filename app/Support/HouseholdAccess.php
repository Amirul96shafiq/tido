<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class HouseholdAccess
{
    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function isPrimary(): bool
    {
        $user = self::user();

        return $user === null || $user->isPrimary();
    }

    public static function isFamilyMember(): bool
    {
        $user = self::user();

        return $user instanceof User && $user->isFamilyMember();
    }

    public static function canManageHouseholdSettings(): bool
    {
        return self::isPrimary();
    }

    public static function canMutateExpense(Expense $invoice): bool
    {
        $user = self::user();

        if ($user === null || $user->isPrimary()) {
            return true;
        }

        if (! $user->isFamilyMember() || $user->family_member_id === null) {
            return false;
        }

        return $invoice->family_member_id !== null
            && (int) $invoice->family_member_id === (int) $user->family_member_id;
    }
}
