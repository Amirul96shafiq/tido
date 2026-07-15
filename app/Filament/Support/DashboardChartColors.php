<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\PaymentMethod;

final class DashboardChartColors
{
    public const PRIMARY_LIGHT = '#FFE2A3';

    public const PRIMARY = '#FFD07D';

    public const PRIMARY_DARK = '#FFA524';

    public const UNKNOWN = '#9CA3AF';

    public static function forPaymentMethod(?PaymentMethod $paymentMethod): string
    {
        return match ($paymentMethod) {
            PaymentMethod::Mastercard, PaymentMethod::Cash => self::PRIMARY_DARK,
            PaymentMethod::Visa, PaymentMethod::PayWithQr => self::PRIMARY,
            PaymentMethod::Mykasih, PaymentMethod::TouchNGo => self::PRIMARY_LIGHT,
            PaymentMethod::Other => self::UNKNOWN,
            default => self::UNKNOWN,
        };
    }

    public static function forSource(mixed $source): string
    {
        return match ($source) {
            'manual' => self::PRIMARY_LIGHT,
            'google_drive' => self::PRIMARY,
            'whatsapp' => self::PRIMARY_DARK,
            default => self::UNKNOWN,
        };
    }
}
