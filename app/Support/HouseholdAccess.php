<?php

declare(strict_types=1);

namespace App\Support;

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
}
