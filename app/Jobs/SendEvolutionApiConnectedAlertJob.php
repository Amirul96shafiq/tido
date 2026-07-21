<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsAppConnectMethod;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendWhatsAppConnectedAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ?string $connectedNumber = null,
        public ?WhatsAppConnectMethod $connectMethod = null,
    ) {
        $this->onQueue('whatsapp');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(WhatsAppNotificationService $waService): void
    {
        $number = config('services.evolution.personal_number');

        if (! is_string($number) || $number === '') {
            Log::warning('SendWhatsAppConnectedAlertJob skipped: PERSONAL_WHATSAPP_NUMBER is not configured');

            return;
        }

        $body = match ($this->connectMethod) {
            WhatsAppConnectMethod::QrCode => 'WhatsApp session reconnected via QR code and is ready for document uploads.',
            WhatsAppConnectMethod::PairingCode => 'WhatsApp session reconnected via pairing code and is ready for document uploads.',
            null => 'WhatsApp session reconnected and is ready for document uploads.',
        };

        if (is_string($this->connectedNumber) && $this->connectedNumber !== '') {
            $body .= "\n\nLinked number: {$this->connectedNumber}.";
        }

        $body .= "\n\nSend a document anytime to start tracking expenses.";

        $sent = $waService->sendMessage(
            $number,
            WhatsAppMessage::compose(
                '✅',
                'Connected',
                $body,
            ),
        );

        if (! $sent) {
            throw new RuntimeException('Evolution sendText failed for WhatsApp connected alert.');
        }
    }
}
