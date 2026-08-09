<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationResource: string
{
    case Profile = 'profile';
    case Expenses = 'expenses';
    case WhatsApp = 'whatsapp';
    case EvolutionApi = 'evolution-api';
    case Budgets = 'budgets';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Profile',
            self::Expenses => 'Expenses',
            self::WhatsApp => 'WhatsApp',
            self::EvolutionApi => 'Evolution API',
            self::Budgets => 'Budgets',
        };
    }

    public function titleSearchPattern(): string
    {
        return match ($this) {
            self::Profile => 'Profile%',
            self::Expenses => 'Receipt%',
            self::WhatsApp => 'WhatsApp%',
            self::EvolutionApi => 'Evolution API%',
            self::Budgets => 'Budget%',
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
