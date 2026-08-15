<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\HouseholdRole;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\User;
use Illuminate\Auth\Access\Response;
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

    public static function canMutateAssigned(?int $familyMemberId): bool
    {
        $user = self::user();

        if ($user === null || $user->isPrimary()) {
            return true;
        }

        if (! $user->isFamilyMember() || $user->family_member_id === null) {
            return false;
        }

        return $familyMemberId !== null
            && (int) $familyMemberId === (int) $user->family_member_id;
    }

    public static function canMutateExpense(Expense $expense): bool
    {
        return self::canMutateAssigned($expense->family_member_id);
    }

    public static function canMutateBudget(Budget $budget): bool
    {
        return self::canMutateAssigned($budget->family_member_id);
    }

    public static function canMutateRecurring(Recurring $recurring): bool
    {
        return self::canMutateAssigned($recurring->family_member_id);
    }

    public static function primaryDisplayName(): string
    {
        $primaryUser = User::query()
            ->where(function ($query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first(['name', 'display_name']);

        if (! $primaryUser instanceof User) {
            return 'Primary';
        }

        return filled($primaryUser->display_name)
            ? (string) $primaryUser->display_name
            : (string) $primaryUser->name;
    }

    public static function memberDisplayName(?FamilyMember $familyMember): string
    {
        if ($familyMember === null) {
            return self::primaryDisplayName();
        }

        return filled($familyMember->display_name)
            ? (string) $familyMember->display_name
            : (string) $familyMember->name;
    }

    public static function assignedOwnerAuthorizationMessage(?FamilyMember $familyMember): string
    {
        return 'Only '.self::memberDisplayName($familyMember).' able to use this CTA button.';
    }

    public static function createDeniedMessage(): string
    {
        return 'Only '.self::primaryDisplayName().' able to use this CTA button.';
    }

    public static function createDeniedResponse(): Response
    {
        return Response::deny(self::createDeniedMessage());
    }
}
