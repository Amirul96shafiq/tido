<?php

declare(strict_types=1);

namespace App\Enums;

enum UserDateFormat: string
{
    case DmySlash = 'd/m/Y';
    case DmyLong = 'd M Y';
    case Iso = 'Y-m-d';

    public function label(): string
    {
        return match ($this) {
            self::DmySlash => '09/07/2026 (d/m/Y)',
            self::DmyLong => '09 Jul 2026 (d M Y)',
            self::Iso => '2026-07-09 (Y-m-d)',
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
}
