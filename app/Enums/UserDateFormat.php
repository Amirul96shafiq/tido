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
        return $this->value;
    }

    public function description(): string
    {
        return now()->format($this->value);
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

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        $descriptions = [];

        foreach (self::cases() as $case) {
            $descriptions[$case->value] = $case->description();
        }

        return $descriptions;
    }
}
