<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurringFrequency: string
{
    case Repeating = 'repeating';
    case Once = 'once';

    public function label(): string
    {
        return match ($this) {
            self::Repeating => 'Repeating',
            self::Once => 'Once',
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
