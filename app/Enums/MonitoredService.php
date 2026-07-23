<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitoredService: string
{
    case App = 'app';
    case Database = 'database';
    case Ollama = 'ollama';
    case Evolution = 'evolution';
    case Queue = 'queue';
    case GoogleDrive = 'google_drive';

    public function label(): string
    {
        return match ($this) {
            self::App => 'Application',
            self::Database => 'Database',
            self::Ollama => 'Ollama',
            self::Evolution => 'Evolution API',
            self::Queue => 'Queue',
            self::GoogleDrive => 'Google Drive',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::App => 10,
            self::Database => 20,
            self::Ollama => 30,
            self::Evolution => 40,
            self::Queue => 50,
            self::GoogleDrive => 60,
        };
    }

    public function isConfigured(): bool
    {
        return match ($this) {
            self::App, self::Database, self::Ollama, self::Evolution, self::Queue => true,
            self::GoogleDrive => self::googleDriveIsConfigured(),
        };
    }

    /**
     * @return list<self>
     */
    public static function configured(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $service): bool => $service->isConfigured(),
        ));
    }

    private static function googleDriveIsConfigured(): bool
    {
        $disk = config('filesystems.disks.google');

        if (! is_array($disk)) {
            return false;
        }

        return filled($disk['clientId'] ?? null)
            && filled($disk['clientSecret'] ?? null)
            && filled($disk['refreshToken'] ?? null)
            && filled($disk['folderId'] ?? null);
    }
}
