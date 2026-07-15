<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case Mastercard = 'mastercard';
    case Visa = 'visa';
    case Mykasih = 'mykasih';
    case Cash = 'cash';
    case PayWithQr = 'pay_with_qr';
    case TouchNGo = 'touchngo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mastercard => 'Mastercard',
            self::Visa => 'Visa',
            self::Mykasih => 'MYKASIH',
            self::Cash => 'Cash',
            self::PayWithQr => 'Pay with QR',
            self::TouchNGo => "Touch 'n Go",
            self::Other => 'Other',
        };
    }

    public function getLabel(): string|Htmlable|null
    {
        return $this->label();
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Mastercard => self::mastercardIcon(),
            self::Visa => self::visaIcon(),
            self::Mykasih => Heroicon::Identification,
            self::Cash => Heroicon::Banknotes,
            self::PayWithQr => Heroicon::QrCode,
            self::TouchNGo => self::touchNGoIcon(),
            self::Other => Heroicon::EllipsisHorizontal,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Visa => 'info',
            self::Mastercard => 'warning',
            self::Mykasih => 'success',
            self::Cash => 'warning',
            self::PayWithQr => 'primary',
            self::TouchNGo => 'primary',
            self::Other => 'gray',
        };
    }

    private static function visaIcon(): Htmlable
    {
        return new HtmlString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.2 6.5h3.6l2.9 11h-3.1L3.2 6.5zm6.4 0h3.3l1.7 7.2L17.2 6.5H21l-4.8 11h-3.4L9.6 6.5z"/></svg>',
        );
    }

    private static function mastercardIcon(): Htmlable
    {
        return new HtmlString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="12" r="5.5"/><circle cx="15" cy="12" r="5.5" fill-opacity="0.55"/></svg>',
        );
    }

    private static function touchNGoIcon(): Htmlable
    {
        return new HtmlString(
            <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M42.451 27.798v8.009a3.946 3.946 0 0 1-3.945 3.947H8.446a3.946 3.946 0 0 1-3.946-3.946V12.193a3.946 3.946 0 0 1 3.945-3.947h30.06a3.946 3.946 0 0 1 3.946 3.946v8.215"/><path d="M36.151 27.798a3.693 3.693 0 0 1-3.693-3.693h0a3.694 3.694 0 0 1 3.692-3.696h6.35a1 1 0 0 1 1 1v5.391a1 1 0 0 1-1 1h-6.349Z"/><circle cx="36.492" cy="24.146" r="1.717"/><path d="M8.246 19.474h5.997M11.244 28.526V19.474"/><path d="M20.445 28.526v-3.734a2.263 2.263 0 0 0-4.526 0"/><path d="M15.918 28.526v-5.997"/><path d="M29.154 22.472a2.999 2.999 0 0 0-5.997 0v3.055a2.999 2.999 0 0 0 5.997 0h-2.999"/></svg>
            SVG,
        );
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

    public static function tryFromAi(mixed $value): ?self
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-', "'"], ['_', '_', ''], $normalized);

        return match ($normalized) {
            'mastercard', 'master', 'master_card' => self::Mastercard,
            'visa' => self::Visa,
            'mykasih', 'my_kasih' => self::Mykasih,
            'cash' => self::Cash,
            'pay_with_qr', 'qr', 'qr_pay', 'qr_payment', 'duitnow_qr', 'duitnow' => self::PayWithQr,
            'touchngo', 'touch_n_go', 'touchngo_ewallet', 'tng', 't_ngo' => self::TouchNGo,
            'debit', 'credit', 'debit_card', 'credit_card' => self::Other,
            'other' => self::Other,
            default => self::tryFrom($normalized),
        };
    }
}
