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
use Illuminate\Database\Eloquent\Builder;
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
        return self::primaryOnlyAccessMessage();
    }

    public static function primaryOnlyAccessMessage(): string
    {
        return 'Only '.self::primaryDisplayName().' can access this feature.';
    }

    public static function createDeniedResponse(): Response
    {
        return Response::deny(self::createDeniedMessage());
    }

    /**
     * Limit budgets/recurrings to owned-or-shared rows for a family sender.
     * Primary senders (null) see all rows.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function constrainSharedOwnership(Builder $query, ?int $familyMemberId): Builder
    {
        if ($familyMemberId === null) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($familyMemberId): void {
            $inner
                ->where('is_shared', true)
                ->orWhere('family_member_id', $familyMemberId);
        });
    }
}
