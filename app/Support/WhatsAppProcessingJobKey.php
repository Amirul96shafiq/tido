<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Expense;

final class WhatsAppProcessingJobKey
{
    public static function uniqueForSeconds(): int
    {
        return max(60, (int) config('services.evolution.webhook_idempotency_ttl_seconds', 604800));
    }

    public static function forMessage(string $messageId, string $jobType): string
    {
        return 'wa:job:'.$jobType.':'.hash('sha256', $messageId);
    }

    public static function forExpense(int $expenseId, string $jobType): string
    {
        return 'wa:job:'.$jobType.':expense:'.$expenseId;
    }

    public static function messageIdForManualBlock(string $messageId, int $blockIndex): string
    {
        if ($blockIndex <= 0) {
            return $messageId;
        }

        return $messageId.':'.$blockIndex;
    }

    public static function textReplySentCacheKey(string $messageId): string
    {
        return 'wa:text-reply-sent:'.hash('sha256', $messageId);
    }

    public static function manualExpenseAlreadyCreated(string $messageId): bool
    {
        return Expense::query()
            ->where(function ($query) use ($messageId): void {
                $query->where('whatsapp_message_id', $messageId)
                    ->orWhere('whatsapp_message_id', 'like', $messageId.':%');
            })
            ->exists();
    }
}
