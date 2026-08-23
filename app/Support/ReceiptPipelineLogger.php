<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class ReceiptPipelineLogger
{
    public static function start(): int
    {
        return hrtime(true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function completed(string $stage, int $startedAt, array $context = []): void
    {
        self::write(
            $stage,
            round(max(0, hrtime(true) - $startedAt) / 1_000_000, 2),
            $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function event(string $stage, array $context = []): void
    {
        self::write($stage, 0.0, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $stage, float $durationMs, array $context): void
    {
        Log::info('Receipt pipeline stage', array_merge([
            'message_id' => null,
            'expense_id' => null,
            'stage' => $stage,
            'queue' => 'unknown',
            'outcome' => 'success',
            'duration_ms' => $durationMs,
        ], $context));
    }
}
