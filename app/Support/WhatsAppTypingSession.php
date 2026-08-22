<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class WhatsAppTypingSession
{
    public static function cacheKey(int $expenseId): string
    {
        return 'wa:typing:expense:'.$expenseId;
    }

    public static function activate(int $expenseId, string $sender): void
    {
        if ($expenseId < 1 || trim($sender) === '') {
            return;
        }

        $ttlSeconds = max(60, (int) config('services.evolution.whatsapp_typing_session_ttl_seconds', 600));

        Cache::put(self::cacheKey($expenseId), [
            'sender' => trim($sender),
            'activated_at' => now()->toIso8601String(),
        ], now()->addSeconds($ttlSeconds));
    }

    public static function isActive(int $expenseId): bool
    {
        if ($expenseId < 1) {
            return false;
        }

        return Cache::has(self::cacheKey($expenseId));
    }

    public static function sender(int $expenseId): ?string
    {
        if ($expenseId < 1) {
            return null;
        }

        $payload = Cache::get(self::cacheKey($expenseId));

        if (! is_array($payload)) {
            return null;
        }

        $sender = trim((string) ($payload['sender'] ?? ''));

        return $sender !== '' ? $sender : null;
    }

    public static function deactivate(int $expenseId): void
    {
        if ($expenseId < 1) {
            return;
        }

        Cache::forget(self::cacheKey($expenseId));
    }
}
