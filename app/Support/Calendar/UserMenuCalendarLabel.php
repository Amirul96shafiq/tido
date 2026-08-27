<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Enums\UserDateFormat;
use App\Helpers\UserDateDisplay;
use Carbon\CarbonInterface;
use Illuminate\Support\HtmlString;

final class UserMenuCalendarLabel
{
    public static function text(?CarbonInterface $date = null): string
    {
        $formattedDate = self::formatDate($date);

        return "Calendar {$formattedDate}";
    }

    public static function html(?CarbonInterface $date = null): HtmlString
    {
        $formattedDate = e(self::formatDate($date));

        return new HtmlString(
            'Calendar <span class="fi-user-menu-calendar-date-pill">'.$formattedDate.'</span>',
        );
    }

    private static function formatDate(?CarbonInterface $date): string
    {
        $resolved = $date ?? now()->timezone(UserDateDisplay::timezone());
        $format = UserDateFormat::menuCalendarPillFormatFor(UserDateDisplay::dateFormat());

        return $resolved->format($format);
    }
}
