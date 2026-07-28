<?php

declare(strict_types=1);

namespace App\Enums;

enum HouseholdRole: string
{
    case Primary = 'primary';
    case FamilyMember = 'family_member';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::FamilyMember => 'Family member',
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
