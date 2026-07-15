<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\TidoBrandCopy;
use Illuminate\Support\HtmlString;

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
            self::Morning => 'Ready to start the day? '.TidoBrandCopy::dashboardActionPhrase(),
            self::Afternoon => 'Ready to keep going? '.TidoBrandCopy::dashboardActionPhrase(),
            self::Evening => 'Ready to wrap up? '.TidoBrandCopy::dashboardActionPhrase(),
        };
    }

    public function subheadingHtml(): HtmlString
    {
        $prefix = match ($this) {
            self::Morning => 'Ready to start the day?',
            self::Afternoon => 'Ready to keep going?',
            self::Evening => 'Ready to wrap up?',
        };

        return new HtmlString($prefix.' '.(string) TidoBrandCopy::dashboardActionPhraseHtml());
    }
}
