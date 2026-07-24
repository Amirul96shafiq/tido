<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;

readonly class ActiveSessionData
{
    public function __construct(
        public string $id,
        public string $deviceClass,
        public string $deviceDetail,
        public CarbonInterface $createdAt,
        public bool $isCurrent,
    ) {}
}
