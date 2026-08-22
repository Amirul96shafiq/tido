<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppSpendingCommandParser;
use App\Support\WhatsAppSpendingReplyBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppTextReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public function __construct(
        public string $senderNumber,
        public string $originalText,
    ) {
        $this->onQueue('whatsapp');
        $this->timeout = max(1, (int) config('services.evolution.timeout', 15)) + 15;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
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
        if (! PhoneNumber::isAllowedWhatsAppSender($this->senderNumber)) {
            Log::info('ProcessWhatsAppTextReplyJob skipped non-allowlisted sender', [
                'sender' => explode('@', $this->senderNumber)[0] ?: $this->senderNumber,
            ]);

            return;
        }

        $reply = $this->buildReply($this->originalText);
        $waService->sendMessage($this->senderNumber, $reply);
    }

    protected function buildReply(string $originalText): string
    {
        $spendingCommand = WhatsAppSpendingCommandParser::parse($originalText);

        if ($spendingCommand !== null) {
            return (new WhatsAppSpendingReplyBuilder(
                $spendingCommand['month'],
                $spendingCommand['mode'],
            ))->build();
        }

        $text = strtolower(trim($originalText));

        if (str_contains($text, 'finance others')) {
            return WhatsAppMessage::financeKeywords();
        }

        if (str_contains($text, 'manual way') || preg_match('/\bmanual\b/u', $text) === 1) {
            return WhatsAppMessage::manualApproach();
        }

        return WhatsAppMessage::help();
    }
}
