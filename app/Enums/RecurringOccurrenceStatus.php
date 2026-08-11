<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurringOccurrenceStatus: string
{
    case Upcoming = 'upcoming';
    case Due = 'due';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Due => 'Due',
            self::Overdue => 'Overdue',
            self::Completed => 'Completed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Upcoming, self::Due, self::Overdue => true,
            self::Completed, self::Skipped => false,
        };
    }
}
