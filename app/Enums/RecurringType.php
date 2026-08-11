<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurringType: string
{
    case FixedBill = 'fixed_bill';
    case VariableBill = 'variable_bill';
    case Subscription = 'subscription';
    case DebtInstalment = 'debt_instalment';
    case TransferInvestment = 'transfer_investment';

    public function label(): string
    {
        return match ($this) {
            self::FixedBill => 'Fixed bill',
            self::VariableBill => 'Variable bill',
            self::Subscription => 'Subscription',
            self::DebtInstalment => 'Debt instalment',
            self::TransferInvestment => 'Transfer / investment',
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
