<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\UserDateFormat;
use App\Models\User;
use Carbon\CarbonInterface;

final class UserDateDisplay
{
    public static function dateFormat(): string
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user->preferredDateFormat();
        }

        return (string) config('app.date_format', UserDateFormat::DmySlash->value);
    }

    public static function dateTimeFormat(): string
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user->preferredDateTimeFormat();
        }

        return (string) config('app.datetime_format', UserDateFormat::DmySlash->value.' H:i');
    }

    public static function datePlaceholder(): string
    {
        return match (self::dateFormat()) {
            UserDateFormat::DmySlash->value => 'dd/mm/yyyy',
            UserDateFormat::DmyLong->value => 'dd M yyyy',
            UserDateFormat::Iso->value => 'yyyy-mm-dd',
            default => self::dateFormat(),
        };
    }

    public static function timePlaceholder(): string
    {
        return 'HH:mm';
    }

    public static function dateTimePlaceholder(): string
    {
        return self::datePlaceholder().' '.self::timePlaceholder();
    }

    public static function pickerPlaceholder(bool $hasDate, bool $hasTime): string
    {
        if ($hasDate && $hasTime) {
            return self::dateTimePlaceholder();
        }

        if ($hasTime) {
            return self::timePlaceholder();
        }

        return self::datePlaceholder();
    }

    public static function timezone(): string
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user->preferredTimezone();
        }

        return (string) config('app.timezone', 'Asia/Kuala_Lumpur');
    }

    public static function gmtOffsetLabel(CarbonInterface $date): string
    {
        $minutes = $date->utcOffset();
        $sign = $minutes >= 0 ? '+' : '-';
        $absoluteMinutes = abs($minutes);
        $hours = intdiv($absoluteMinutes, 60);
        $remainingMinutes = $absoluteMinutes % 60;

        if ($remainingMinutes === 0) {
            return 'GMT'.$sign.$hours;
        }

        return sprintf('GMT%s%d:%02d', $sign, $hours, $remainingMinutes);
    }
}
