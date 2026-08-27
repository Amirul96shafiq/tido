<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Enums\GoogleOAuthLoginEvent;
use App\Models\GoogleOAuthLoginLog;
use App\Models\User;

final class GoogleOAuthLoginLogService
{
    public function log(
        GoogleOAuthLoginEvent $event,
        string $status,
        ?User $user = null,
        ?string $message = null,
    ): GoogleOAuthLoginLog {
        return GoogleOAuthLoginLog::query()->create([
            'event' => $event,
            'status' => $status,
            'user_id' => $user?->getKey(),
            'message' => $message,
        ]);
    }
}
