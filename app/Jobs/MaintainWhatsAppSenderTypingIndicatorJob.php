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

class MaintainWhatsAppSenderTypingIndicatorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $senderNumber)
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

        if (! WhatsAppTypingSession::isSenderActive($this->senderNumber)) {
            return;
        }

        $sender = WhatsAppTypingSession::senderNumber($this->senderNumber);

        if ($sender === null) {
            WhatsAppTypingSession::deactivateSender($this->senderNumber);

            return;
        }

        $refreshSeconds = max(1, (int) config('services.evolution.whatsapp_typing_refresh_seconds', 15));

        if (WhatsAppTypingSession::isSenderActive($this->senderNumber)) {
            self::dispatch($this->senderNumber)
                ->delay(now()->addSeconds($refreshSeconds));
        }

        $waService->sendTyping($sender);
    }
}
