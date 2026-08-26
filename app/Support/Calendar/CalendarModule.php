<?php

declare(strict_types=1);

namespace App\Support\Calendar;

enum CalendarModule: string
{
    case Finances = 'finances';
    case Household = 'household';
    case Training = 'training';
    case Health = 'health';
    case Task = 'task';

    public function label(): string
    {
        return match ($this) {
            self::Finances => 'Finances',
            self::Household => 'Household',
            self::Training => 'Training',
            self::Health => 'Health',
            self::Task => 'Task',
        };
    }
}
