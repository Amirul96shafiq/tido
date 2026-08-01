<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class PdfInspectionException extends RuntimeException
{
    public const DEPENDENCY_MISSING = 'pdf_dependency_missing';

    public const PASSWORD_PROTECTED = 'pdf_password_protected';

    public const UNREADABLE = 'pdf_unreadable';

    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
