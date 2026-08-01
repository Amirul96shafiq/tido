<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class WhatsAppLid
{
    private const PENDING_INDEX_KEY = 'wa:unlinked-lids:index';

    private const PENDING_ITEM_PREFIX = 'wa:unlinked-lid:';

    private const PENDING_TTL_DAYS = 30;

    /**
     * Normalize a WhatsApp LID to digits only (e.g. 3693839708391).
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $parts = explode('@', $trimmed, 2);
        $local = $parts[0];
        $domain = isset($parts[1]) ? strtolower($parts[1]) : null;

        if ($domain !== null && $domain !== 'lid') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $local) ?? '';

        if ($digits === '' || strlen($digits) < 5) {
            return null;
        }

        // Malaysian phone numbers are not LIDs.
        if (PhoneNumber::normalize($digits) !== null) {
            return null;
        }

        return $digits;
    }

    public static function isLidIdentifier(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        if (str_ends_with(strtolower($trimmed), '@lid')) {
            return self::normalize($trimmed) !== null;
        }

        if (str_contains($trimmed, '@')) {
            return false;
        }

        return self::normalize($trimmed) !== null;
    }

    public static function allowlistedPhoneForLid(string $lid): ?string
    {
        $normalizedLid = self::normalize($lid);

        if ($normalizedLid === null) {
            return null;
        }

        $primary = PhoneNumber::primaryUser();

        if ($primary !== null && $primary->whatsapp_lid === $normalizedLid) {
            return PhoneNumber::normalize(is_string($primary->phone) ? $primary->phone : null);
        }

        $member = FamilyMember::query()
            ->allowlisted()
            ->where('whatsapp_lid', $normalizedLid)
            ->first(['phone']);

        if ($member === null) {
            return null;
        }

        return PhoneNumber::normalize($member->phone);
    }

    public static function isLinked(string $lid): bool
    {
        $normalizedLid = self::normalize($lid);

        if ($normalizedLid === null) {
            return false;
        }

        if (User::query()->where('whatsapp_lid', $normalizedLid)->exists()) {
            return true;
        }

        return FamilyMember::query()->where('whatsapp_lid', $normalizedLid)->exists();
    }

    public static function rememberUnlinked(string $remoteJid, ?string $pushName = null): void
    {
        $lid = self::normalize($remoteJid);

        if ($lid === null || self::isLinked($lid)) {
            return;
        }

        $ttl = now()->addDays(self::PENDING_TTL_DAYS);

        Cache::put(self::PENDING_ITEM_PREFIX.$lid, [
            'lid' => $lid,
            'push_name' => filled($pushName) ? trim((string) $pushName) : null,
            'seen_at' => now()->toIso8601String(),
        ], $ttl);

        /** @var list<string> $index */
        $index = Cache::get(self::PENDING_INDEX_KEY, []);

        if (! in_array($lid, $index, true)) {
            $index[] = $lid;
            Cache::put(self::PENDING_INDEX_KEY, array_values($index), $ttl);
        }
    }

    /**
     * @return list<array{lid: string, push_name: string|null, seen_at: string|null}>
     */
    public static function pendingUnlinked(): array
    {
        /** @var list<string> $index */
        $index = Cache::get(self::PENDING_INDEX_KEY, []);
        $pending = [];
        $alive = [];

        foreach ($index as $lid) {
            if (self::isLinked($lid)) {
                Cache::forget(self::PENDING_ITEM_PREFIX.$lid);

                continue;
            }

            /** @var array{lid?: string, push_name?: string|null, seen_at?: string|null}|null $item */
            $item = Cache::get(self::PENDING_ITEM_PREFIX.$lid);

            if (! is_array($item)) {
                continue;
            }

            $alive[] = $lid;
            $pending[] = [
                'lid' => $lid,
                'push_name' => isset($item['push_name']) && is_string($item['push_name'])
                    ? $item['push_name']
                    : null,
                'seen_at' => isset($item['seen_at']) && is_string($item['seen_at'])
                    ? $item['seen_at']
                    : null,
            ];
        }

        Cache::put(self::PENDING_INDEX_KEY, $alive, now()->addDays(self::PENDING_TTL_DAYS));

        return $pending;
    }

    public static function forgetPending(string $lid): void
    {
        $normalizedLid = self::normalize($lid);

        if ($normalizedLid === null) {
            return;
        }

        Cache::forget(self::PENDING_ITEM_PREFIX.$normalizedLid);

        /** @var list<string> $index */
        $index = Cache::get(self::PENDING_INDEX_KEY, []);
        $index = array_values(array_filter(
            $index,
            static fn (string $entry): bool => $entry !== $normalizedLid,
        ));
        Cache::put(self::PENDING_INDEX_KEY, $index, now()->addDays(self::PENDING_TTL_DAYS));
    }

    /**
     * @param  'primary'|string  $target  primary or family:{id}
     */
    public static function link(string $lid, string $target): void
    {
        $normalizedLid = self::normalize($lid);

        if ($normalizedLid === null) {
            throw new InvalidArgumentException('Invalid WhatsApp LID.');
        }

        self::clearLidEverywhere($normalizedLid);

        if ($target === 'primary') {
            $primary = PhoneNumber::primaryUser();

            if ($primary === null) {
                throw new InvalidArgumentException('Primary user not found.');
            }

            if (PhoneNumber::normalize(is_string($primary->phone) ? $primary->phone : null) === null) {
                throw new InvalidArgumentException('Primary Profile WhatsApp number is required before linking a LID.');
            }

            $primary->forceFill(['whatsapp_lid' => $normalizedLid])->save();
            self::forgetPending($normalizedLid);

            return;
        }

        if (! str_starts_with($target, 'family:')) {
            throw new InvalidArgumentException('Invalid LID link target.');
        }

        $familyMemberId = (int) substr($target, strlen('family:'));

        if ($familyMemberId < 1) {
            throw new InvalidArgumentException('Invalid family member target.');
        }

        $member = FamilyMember::query()->allowlisted()->whereKey($familyMemberId)->first();

        if ($member === null) {
            throw new InvalidArgumentException('Allowlisted family member not found.');
        }

        $member->forceFill(['whatsapp_lid' => $normalizedLid])->save();
        self::forgetPending($normalizedLid);
    }

    public static function unlink(string $lid): void
    {
        $normalizedLid = self::normalize($lid);

        if ($normalizedLid === null) {
            return;
        }

        self::clearLidEverywhere($normalizedLid);
    }

    private static function clearLidEverywhere(string $normalizedLid): void
    {
        User::query()
            ->where('whatsapp_lid', $normalizedLid)
            ->update(['whatsapp_lid' => null]);

        FamilyMember::query()
            ->where('whatsapp_lid', $normalizedLid)
            ->update(['whatsapp_lid' => null]);
    }
}
