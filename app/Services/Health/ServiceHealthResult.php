<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\ServiceHealthStatus;

readonly class ServiceHealthResult
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public ServiceHealthStatus $status,
        public ?int $latencyMs = null,
        public ?array $meta = null,
    ) {}

    public function message(): ?string
    {
        $message = data_get($this->meta, 'message');

        return is_string($message) && $message !== '' ? $message : null;
    }
}
