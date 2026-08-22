<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\MaintainWhatsAppSenderTypingIndicatorJob;
use App\Jobs\MaintainWhatsAppTypingIndicatorJob;

final class WhatsAppTypingCoordinator
{
    public static function startExpenseTyping(int $expenseId, string $sender): void
    {
        if (! self::typingEnabled()) {
            return;
        }

        if (WhatsAppTypingSession::isActive($expenseId)) {
            return;
        }

        WhatsAppTypingSession::activate($expenseId, $sender);
        MaintainWhatsAppTypingIndicatorJob::dispatch($expenseId);
    }

    public static function startSenderTyping(string $sender): void
    {
        if (! self::typingEnabled()) {
            return;
        }

        if (WhatsAppTypingSession::isSenderActive($sender)) {
            return;
        }

        WhatsAppTypingSession::activateSender($sender);
        MaintainWhatsAppSenderTypingIndicatorJob::dispatch($sender);
    }

    public static function handoffSenderToExpense(int $expenseId, string $sender): void
    {
        self::startExpenseTyping($expenseId, $sender);
        WhatsAppTypingSession::deactivateSender($sender);
    }

    public static function stopSenderTyping(string $sender): void
    {
        WhatsAppTypingSession::deactivateSender($sender);
    }

    protected static function typingEnabled(): bool
    {
        return (bool) config('services.evolution.whatsapp_typing_enabled', true);
    }
}
