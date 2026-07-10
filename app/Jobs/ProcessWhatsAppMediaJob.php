<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $senderNumber,
        public string $remoteJid,
        public string $messageId,
        public bool $fromMe,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 60];
    }

    public function handle(WhatsAppNotificationService $waService): void
    {
        $filename = 'wa_'.$this->messageId.'.jpg';
        $localPath = 'receipts/'.$filename;

        if ($this->invoiceAlreadyExists($filename)) {
            Log::info('WhatsApp media job skipped duplicate message', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $binaryData = $this->downloadMedia();

        if ($binaryData === null) {
            $attempt = $this->attemptNumber();

            $waService->sendMessage(
                $this->senderNumber,
                WhatsAppMessage::receiptUploadFailed($attempt, $this->tries),
            );

            throw new RuntimeException('Failed to download WhatsApp receipt media.');
        }

        Storage::put($localPath, $binaryData);

        $invoice = Invoice::create([
            'merchant_name' => 'Pending AI Extraction...',
            'date_time' => now(),
            'subtotal' => 0.00,
            'total_tax' => 0.00,
            'total_amount' => 0.00,
            'currency' => 'MYR',
            'source' => 'whatsapp',
            'status' => 'pending',
            'image_path' => $localPath,
            'original_filename' => $filename,
        ]);

        $waService->sendMessage(
            $this->senderNumber,
            WhatsAppMessage::compose(
                '📥',
                'Receipt received',
                "Your receipt is queued for AI parsing.\n\nWe will update you shortly.",
            ),
        );

        Log::info('WhatsApp receipt media processed', [
            'invoice_id' => $invoice->id,
            'message_id' => $this->messageId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessWhatsAppMediaJob failed after maximum retries', [
            'message_id' => $this->messageId,
            'sender' => $this->senderNumber,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function attemptNumber(): int
    {
        return $this->attempts();
    }

    protected function invoiceAlreadyExists(string $filename): bool
    {
        $localPath = 'receipts/'.$filename;

        if (Storage::exists($localPath)) {
            return true;
        }

        return Invoice::query()
            ->where('original_filename', $filename)
            ->exists();
    }

    protected function downloadMedia(): ?string
    {
        $instanceName = (string) config('services.evolution.instance_name');
        $apiUrl = rtrim((string) config('services.evolution.api_url'), '/');
        $apiKey = (string) config('services.evolution.api_key');

        $response = Http::withHeaders(['apikey' => $apiKey])
            ->post("{$apiUrl}/chat/getBase64FromMediaMessage/{$instanceName}", [
                'message' => [
                    'key' => [
                        'remoteJid' => $this->remoteJid,
                        'fromMe' => $this->fromMe,
                        'id' => $this->messageId,
                    ],
                ],
                'convertToMp4' => false,
            ]);

        if ($response->failed()) {
            Log::error('Failed to retrieve media from Evolution API', [
                'message_id' => $this->messageId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $body = $response->json();
        $base64Data = $body['base64'] ?? '';

        if ($base64Data === '') {
            Log::error('Evolution API media response did not contain base64', [
                'message_id' => $this->messageId,
                'response' => $body,
            ]);

            return null;
        }

        if (str_contains($base64Data, ',')) {
            $base64Data = explode(',', $base64Data, 2)[1];
        }

        $binaryData = base64_decode($base64Data, true);

        if ($binaryData === false) {
            Log::error('Evolution API media response contained invalid base64', [
                'message_id' => $this->messageId,
            ]);

            return null;
        }

        return $binaryData;
    }
}
