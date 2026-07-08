<?php

declare(strict_types=1);

namespace App\Enums;

enum UserLocale: string
{
    case En = 'en';
    case Ms = 'ms';

    public function label(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Ms => 'Bahasa Malaysia',
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
