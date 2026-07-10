<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Mastercard = 'mastercard';
    case Visa = 'visa';
    case Mykasih = 'mykasih';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mastercard => 'Mastercard',
            self::Visa => 'Visa',
            self::Mykasih => 'MYKASIH',
            self::Cash => 'Cash',
            self::Other => 'Other',
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
