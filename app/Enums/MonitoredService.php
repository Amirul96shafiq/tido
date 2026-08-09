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

    public function label(): string
    {
        return match ($this) {
            self::App => 'Application',
            self::Database => 'Database',
            self::Ollama => 'Ollama',
            self::Evolution => 'Evolution API',
            self::Queue => 'Queue',
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
        };
    }

    public function isConfigured(): bool
    {
        return match ($this) {
            self::App, self::Database, self::Ollama, self::Evolution, self::Queue => true,
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
}
