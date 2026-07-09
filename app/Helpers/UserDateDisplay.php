<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\UserDateFormat;
use App\Models\User;

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
}
