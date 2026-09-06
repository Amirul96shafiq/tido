<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Enums\GoogleOAuthLoginEvent;
use App\Models\GoogleOAuthLoginLog;
use App\Models\User;
use App\Support\CurrentHousehold;

final class GoogleOAuthLoginLogService
{
    public function log(
        GoogleOAuthLoginEvent $event,
        string $status,
        ?User $user = null,
        ?string $message = null,
        ?int $householdId = null,
    ): GoogleOAuthLoginLog {
        $householdId ??= $user?->household_id
            ?? CurrentHousehold::id();

        return GoogleOAuthLoginLog::query()->withoutGlobalScopes()->create([
            'household_id' => $householdId,
            'event' => $event,
            'status' => $status,
            'user_id' => $user?->getKey(),
            'message' => $message,
        ]);
    }
}
