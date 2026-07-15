<?php

declare(strict_types=1);

namespace App\Enums;

enum TimeOfDayPeriod: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';

    public function emoji(): string
    {
        return match ($this) {
            self::Morning => '☀️',
            self::Afternoon => '🌤️',
            self::Evening => '🌙',
        };
    }

    public function greeting(): string
    {
        return match ($this) {
            self::Morning => 'Good Morning',
            self::Afternoon => 'Good Afternoon',
            self::Evening => 'Good Evening',
        };
    }

    public function subheading(): string
    {
        return match ($this) {
            self::Morning => 'Ready to start the day? Start by tidying up your files, then get it done.',
            self::Afternoon => 'Ready to keep going? Start by tidying up your files, then get it done.',
            self::Evening => 'Ready to wrap up? Start by tidying up your files, then get it done.',
        };
    }
}
