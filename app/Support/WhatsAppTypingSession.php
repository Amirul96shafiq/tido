<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class WhatsAppTypingSession
{
    public static function cacheKey(int $expenseId, ?int $householdId = null): string
    {
        return 'wa:typing:expense:'.($householdId ?? CurrentHousehold::id() ?? 0).':'.$expenseId;
    }

    public static function senderCacheKey(string $sender, ?int $householdId = null): string
    {
        $normalized = PhoneNumber::normalize(explode('@', $sender, 2)[0]) ?? trim($sender);

        return 'wa:typing:sender:'.($householdId ?? CurrentHousehold::id() ?? 0).':'.$normalized;
    }

    public static function activate(int $expenseId, string $sender, ?int $householdId = null): void
    {
        if ($expenseId < 1 || trim($sender) === '') {
            return;
        }

        $ttlSeconds = self::sessionTtlSeconds();

        Cache::put(self::cacheKey($expenseId, $householdId), [
            'sender' => trim($sender),
            'activated_at' => now()->toIso8601String(),
        ], now()->addSeconds($ttlSeconds));
    }

    public static function activateSender(string $sender, ?int $householdId = null): void
    {
        $normalized = PhoneNumber::normalize(explode('@', $sender, 2)[0]) ?? trim($sender);

        if ($normalized === '') {
            return;
        }

        Cache::put(self::senderCacheKey($sender, $householdId), [
            'sender' => $normalized,
            'activated_at' => now()->toIso8601String(),
        ], now()->addSeconds(self::sessionTtlSeconds()));
    }

    public static function isActive(int $expenseId, ?int $householdId = null): bool
    {
        if ($expenseId < 1) {
            return false;
        }

        return Cache::has(self::cacheKey($expenseId, $householdId));
    }

    public static function isSenderActive(string $sender, ?int $householdId = null): bool
    {
        $normalized = PhoneNumber::normalize(explode('@', $sender, 2)[0]) ?? trim($sender);

        if ($normalized === '') {
            return false;
        }

        return Cache::has(self::senderCacheKey($sender, $householdId));
    }

    public static function sender(int $expenseId, ?int $householdId = null): ?string
    {
        if ($expenseId < 1) {
            return null;
        }

        $payload = Cache::get(self::cacheKey($expenseId, $householdId));

        if (! is_array($payload)) {
            return null;
        }

        $sender = trim((string) ($payload['sender'] ?? ''));

        return $sender !== '' ? $sender : null;
    }

    public static function senderNumber(string $sender, ?int $householdId = null): ?string
    {
        if (! self::isSenderActive($sender, $householdId)) {
            return null;
        }

        $payload = Cache::get(self::senderCacheKey($sender, $householdId));

        if (! is_array($payload)) {
            return null;
        }

        $normalized = trim((string) ($payload['sender'] ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    public static function deactivate(int $expenseId, ?int $householdId = null): void
    {
        if ($expenseId < 1) {
            return;
        }

        Cache::forget(self::cacheKey($expenseId, $householdId));
    }

    public static function deactivateSender(string $sender, ?int $householdId = null): void
    {
        Cache::forget(self::senderCacheKey($sender, $householdId));
    }

    protected static function sessionTtlSeconds(): int
    {
        return max(60, (int) config('services.evolution.whatsapp_typing_session_ttl_seconds', 600));
    }
}
