<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Request/job-scoped current household for global query scopes.
 *
 * When unset, BelongsToHousehold does not filter (login, migrations, system jobs
 * must call {@see set()} explicitly before tenant queries).
 */
final class CurrentHousehold
{
    private static ?int $id = null;

    public static function id(): ?int
    {
        return self::$id;
    }

    public static function set(?int $householdId): void
    {
        self::$id = $householdId;
    }

    public static function clear(): void
    {
        self::$id = null;
    }

    public static function requireId(): int
    {
        if (self::$id === null) {
            throw new \RuntimeException('Current household is not set.');
        }

        return self::$id;
    }
}
