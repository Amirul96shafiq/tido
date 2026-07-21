<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EvolutionApiConnectMethod;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendEvolutionApiConnectedAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ?string $connectedNumber = null,
        public ?EvolutionApiConnectMethod $connectMethod = null,
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
            Log::warning('SendEvolutionApiConnectedAlertJob skipped: PERSONAL_WHATSAPP_NUMBER is not configured');

            return;
        }

        $body = match ($this->connectMethod) {
            EvolutionApiConnectMethod::QrCode => 'EvolutionAPI session reconnected via QR code and is ready for document uploads.',
            EvolutionApiConnectMethod::PairingCode => 'EvolutionAPI session reconnected via pairing code and is ready for document uploads.',
            null => 'EvolutionAPI session reconnected and is ready for document uploads.',
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
            throw new RuntimeException('Evolution sendText failed for EvolutionAPI connected alert.');
        }
    }
}
