<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Backup;

final readonly class CreatedBackup
{
    public function __construct(
        public Backup $backup,
        public string $restoreToken,
    ) {}
}
