<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceHealthStatus: string
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::Degraded => 'Degraded',
            self::Down => 'Down',
            self::Unknown => 'No data',
        };
    }

    public function severity(): int
    {
        return match ($this) {
            self::Unknown => 0,
            self::Operational => 1,
            self::Degraded => 2,
            self::Down => 3,
        };
    }

    public function isWorseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    public static function worst(self ...$statuses): self
    {
        $worst = self::Unknown;

        foreach ($statuses as $status) {
            if ($status->isWorseThan($worst)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Operational => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
            self::Unknown => 'gray',
        };
    }

    public function barColorClass(): string
    {
        return match ($this) {
            self::Operational => 'bg-emerald-500',
            self::Degraded => 'bg-warning-500',
            self::Down => 'bg-danger-500',
            self::Unknown => 'bg-gray-300 dark:bg-gray-600',
        };
    }

    public function iconColorClass(): string
    {
        return match ($this) {
            self::Operational => 'text-emerald-500',
            self::Degraded => 'text-warning-500',
            self::Down => 'text-danger-500',
            self::Unknown => 'text-gray-400',
        };
    }
}
