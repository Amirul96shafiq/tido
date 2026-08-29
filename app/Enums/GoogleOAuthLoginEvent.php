<?php

declare(strict_types=1);

namespace App\Enums;

enum GoogleOAuthLoginEvent: string
{
    case SignIn = 'sign_in';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::SignIn => 'Sign in',
            self::Failed => 'Failed',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::SignIn => 'success',
            self::Failed => 'danger',
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
