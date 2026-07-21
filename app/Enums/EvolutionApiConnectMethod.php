<?php

declare(strict_types=1);

namespace App\Enums;

enum EvolutionApiConnectMethod: string
{
    case QrCode = 'qr_code';
    case PairingCode = 'pairing_code';

    public function label(): string
    {
        return match ($this) {
            self::QrCode => 'QR code',
            self::PairingCode => 'pairing code',
        };
    }

    public function connectedViaPhrase(): string
    {
        return 'via '.$this->label();
    }

    /**
     * Label WhatsApp shows under Linked Devices for this connect path.
     */
    public function linkedDeviceLabel(string $configuredQrLabel): string
    {
        return match ($this) {
            self::QrCode => $configuredQrLabel,
            self::PairingCode => 'Google Chrome (Mac OS)',
        };
    }
}
