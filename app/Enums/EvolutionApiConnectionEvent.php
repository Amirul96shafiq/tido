<?php

declare(strict_types=1);

namespace App\Enums;

enum WhatsAppConnectionEvent: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Logout = 'logout';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Disconnected => 'Disconnected',
            self::Logout => 'Logged out',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::Disconnected => 'warning',
            self::Logout => 'danger',
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
