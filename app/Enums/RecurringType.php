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

    public function description(): string
    {
        return match ($this) {
            self::FixedBill => 'Same or similar amount each cycle.',
            self::VariableBill => 'Amount changes between cycles.',
            self::Subscription => 'Recurring product or service.',
            self::DebtInstalment => 'Fixed number of repayments.',
            self::TransferInvestment => 'Savings or investment contribution.',
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
