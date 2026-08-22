<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppTypingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class MaintainWhatsAppTypingIndicatorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $expenseId)
    {
        $this->onQueue('whatsapp');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('evolution-send')];
    }

    public function handle(WhatsAppNotificationService $waService): void
    {
        if (! (bool) config('services.evolution.whatsapp_typing_enabled', true)) {
            return;
        }

        if (! WhatsAppTypingSession::isActive($this->expenseId)) {
            return;
        }

        $sender = WhatsAppTypingSession::sender($this->expenseId);

        if ($sender === null) {
            WhatsAppTypingSession::deactivate($this->expenseId);

            return;
        }

        $refreshSeconds = max(1, (int) config('services.evolution.whatsapp_typing_refresh_seconds', 15));

        if (WhatsAppTypingSession::isActive($this->expenseId)) {
            self::dispatch($this->expenseId)
                ->delay(now()->addSeconds($refreshSeconds));
        }

        $waService->sendTyping($sender);
    }
}
