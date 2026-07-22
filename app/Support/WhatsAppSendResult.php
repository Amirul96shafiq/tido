<?php

declare(strict_types=1);

namespace App\Support;

final readonly class WhatsAppSendResult
{
    public function __construct(
        public bool $ok,
        public string $reason = 'ok',
        public ?string $detail = null,
        public ?int $status = null,
    ) {}

    public static function success(): self
    {
        return new self(ok: true);
    }

    public static function failure(string $reason, ?string $detail = null, ?int $status = null): self
    {
        return new self(ok: false, reason: $reason, detail: $detail, status: $status);
    }
}
