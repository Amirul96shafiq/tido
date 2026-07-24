<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationResource: string
{
    case Profile = 'profile';
    case Invoices = 'invoices';
    case WhatsApp = 'whatsapp';
    case EvolutionApi = 'evolution-api';
    case Budgets = 'budgets';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Profile',
            self::Invoices => 'Invoices',
            self::WhatsApp => 'WhatsApp',
            self::EvolutionApi => 'Evolution API',
            self::Budgets => 'Budgets',
        };
    }

    public function titleSearchPattern(): string
    {
        return match ($this) {
            self::Profile => 'Profile%',
            self::Invoices => 'Receipt%',
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
