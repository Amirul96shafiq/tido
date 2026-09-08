<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class WhatsAppWebhookIdempotency
{
    /**
     * Atomically claim a WhatsApp message ID for webhook processing.
     * Returns true when this is the first claim; false on replay.
     */
    public static function claim(string $messageId, ?int $householdId = null): bool
    {
        $ttl = max(60, (int) config('services.evolution.webhook_idempotency_ttl_seconds', 604800));

        return Cache::add(self::cacheKey($messageId, $householdId), true, $ttl);
    }

    public static function cacheKey(string $messageId, ?int $householdId = null): string
    {
        return 'wa:webhook:msg:'.($householdId ?? CurrentHousehold::id() ?? 0).':'.hash('sha256', $messageId);
    }
}
