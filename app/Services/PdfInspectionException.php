<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class PdfInspectionException extends RuntimeException
{
    public const DEPENDENCY_MISSING = 'pdf_dependency_missing';

    public const PASSWORD_PROTECTED = 'pdf_password_protected';

    public const UNREADABLE = 'pdf_unreadable';

    public static function reasonFromProcessOutput(string $output): string
    {
        $output = Str::lower($output);

        $dependencyMissing = Str::contains($output, [
            'cannot find the path',
            'could not find',
            'not found',
            'not recognized',
            'no such file',
            'the system cannot find the file specified',
            'filename, directory name, or volume label syntax is incorrect',
        ]);
        $passwordProtected = Str::contains($output, [
            'incorrect password',
            'password protected',
            'encrypted',
        ]);

        return match (true) {
            $dependencyMissing => self::DEPENDENCY_MISSING,
            $passwordProtected => self::PASSWORD_PROTECTED,
            default => self::UNREADABLE,
        };
    }

    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
