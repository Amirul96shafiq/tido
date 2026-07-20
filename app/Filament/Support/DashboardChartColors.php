<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\PaymentMethod;

final class DashboardChartColors
{
    public const PRIMARY_LIGHT = '#FFE2A3';

    public const PRIMARY = '#FFD07D';

    public const PRIMARY_DARK = '#FFA524';

    public const UNKNOWN = '#9CA3AF';

    public static function forPaymentMethod(?PaymentMethod $paymentMethod): string
    {
        if ($paymentMethod !== null && filled($paymentMethod->color)) {
            return (string) $paymentMethod->color;
        }

        return self::UNKNOWN;
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
