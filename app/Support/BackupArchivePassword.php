<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class BackupArchivePassword
{
    public const MINIMUM_LENGTH = 32;

    public const UNAVAILABLE_MESSAGE = 'Backup archive encryption is not configured.';

    public static function isValid(mixed $credential): bool
    {
        if (! is_string($credential)) {
            return false;
        }

        $credential = trim($credential);

        if ($credential === '' || strlen($credential) < self::MINIMUM_LENGTH) {
            return false;
        }

        if (str_starts_with($credential, '<') && str_ends_with($credential, '>')) {
            return false;
        }

        return ! in_array(strtolower($credential), [
            'change-me',
            'changeme',
            'replace-me',
            'replace-this',
            'your-backup-password',
            'your-archive-password',
        ], true);
    }

    public static function require(): string
    {
        $password = config('backup.backup.password');

        if (! self::isValid($password)) {
            throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
        }

        return trim((string) $password);
    }
}
