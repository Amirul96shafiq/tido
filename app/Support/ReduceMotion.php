<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class ReduceMotion
{
    public static function enabled(?User $user = null): bool
    {
        $resolved = $user ?? Auth::user();

        if (! $resolved instanceof User) {
            return false;
        }

        return (bool) $resolved->getAttribute('reduce_motion');
    }
}
