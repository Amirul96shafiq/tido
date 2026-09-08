<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('household.{householdId}.expenses', function (User $user, int|string $householdId): bool {
    if ((int) $user->household_id !== (int) $householdId) {
        return false;
    }

    return $user->canAccessPanel(Filament::getPanel('admin'));
});
