<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TimeOfDayPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\HtmlString;

final class TimeOfDayGreeting
{
    public static function period(CarbonInterface $now): TimeOfDayPeriod
    {
        $hour = (int) $now->format('G');

        return match (true) {
            $hour >= 5 && $hour < 12 => TimeOfDayPeriod::Morning,
            $hour >= 12 && $hour < 18 => TimeOfDayPeriod::Afternoon,
            default => TimeOfDayPeriod::Evening,
        };
    }

    public static function emoji(CarbonInterface $now): string
    {
        return self::period($now)->emoji();
    }

    public static function greeting(CarbonInterface $now): string
    {
        return self::period($now)->greeting();
    }

    public static function subheading(CarbonInterface $now): string
    {
        return self::period($now)->subheading();
    }

    public static function headingFor(CarbonInterface $now, string $name): string
    {
        $period = self::period($now);
        $shortName = self::shortenName($name);

        return sprintf('%s, %s %s', $period->greeting(), $shortName, $period->emoji());
    }

    public static function headingHtmlFor(CarbonInterface $now, string $name): HtmlString
    {
        $period = self::period($now);
        $shortName = e(self::shortenName($name));

        return new HtmlString(sprintf(
            '%s, <span class="text-primary-600 dark:text-primary-400">%s</span> %s',
            e($period->greeting()),
            $shortName,
            $period->emoji(),
        ));
    }

    public static function shortenName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if ($parts === []) {
            return $name;
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $firstName = array_shift($parts);
        $initials = array_map(
            static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)).'.',
            $parts,
        );

        return $firstName.' '.implode(' ', $initials);
    }
}
