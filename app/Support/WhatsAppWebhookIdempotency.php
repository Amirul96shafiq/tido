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
    public static function claim(string $messageId): bool
    {
        $ttl = max(60, (int) config('services.evolution.webhook_idempotency_ttl_seconds', 604800));

        return Cache::add(self::cacheKey($messageId), true, $ttl);
    }

    public static function cacheKey(string $messageId): string
    {
        return 'wa:webhook:msg:'.hash('sha256', $messageId);
    }
}
