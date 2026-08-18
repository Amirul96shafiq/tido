<?php

declare(strict_types=1);

namespace App\Enums;

enum OllamaDetectionState: string
{
    case Running = 'running';
    case InstalledStopped = 'installed_stopped';
    case NotInstalled = 'not_installed';
    case RemoteUnreachable = 'remote_unreachable';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::InstalledStopped => 'Installed but stopped',
            self::NotInstalled => 'Not installed',
            self::RemoteUnreachable => 'Unreachable',
        };
    }
}
