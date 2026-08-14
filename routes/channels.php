<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('household.expenses', function (User $user): bool {
    return $user->canAccessPanel(Filament::getPanel('admin'));
});
