<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PdfInspectionException;
use App\Services\PdfPageInspector;
use App\Services\WhatsAppNotificationService;
use App\Support\InvoiceSenderAttribution;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $senderNumber,
        public string $remoteJid,
        public string $messageId,
        public bool $fromMe,
        public string $mediaType = 'image',
        public ?string $declaredMimeType = null,
        public ?string $originalFilename = null,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 60];
    }

    public function handle(
        WhatsAppNotificationService $waService,
        PdfPageInspector $pdfPageInspector,
    ): void {
        if ($this->messageAlreadyHandled()) {
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

        $originalFilename = $this->safeOriginalFilename();
        $maximumBytes = max(1, (int) config('services.documents.max_bytes', 10 * 1024 * 1024));

        if (strlen($binaryData) > $maximumBytes) {
            if ($this->mediaType === 'pdf') {
                $this->registerRejectedDocument(
                    filename: $originalFilename,
                    mimeType: 'application/pdf',
                    reason: 'pdf_size_limit',
                );

                return;
            }

            throw new RuntimeException('WhatsApp media exceeds the configured file-size limit.');
        }

        $detectedMimeType = $this->detectMimeType($binaryData);

        if ($this->mediaType === 'pdf' && $detectedMimeType !== 'application/pdf') {
            $this->registerRejectedDocument(
                filename: $originalFilename,
                mimeType: $detectedMimeType,
                reason: PdfInspectionException::UNREADABLE,
            );

            return;
        }

        if ($this->mediaType !== 'pdf' && ! in_array($detectedMimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
        ], true)) {
            throw new RuntimeException('Unsupported WhatsApp receipt image type.');
        }

        $pageCount = null;

        if ($detectedMimeType === 'application/pdf') {
            try {
                $pageCount = $pdfPageInspector->pageCount($binaryData);
            } catch (PdfInspectionException $exception) {
                $this->registerRejectedDocument(
                    filename: $originalFilename,
                    mimeType: $detectedMimeType,
                    reason: $exception->reason,
                );

                return;
            }

            $maximumPages = max(1, (int) config('services.documents.max_pdf_pages', 3));

            if ($pageCount > $maximumPages) {
                $this->registerRejectedDocument(
                    filename: $originalFilename,
                    mimeType: $detectedMimeType,
                    reason: 'pdf_page_limit',
                    pageCount: $pageCount,
                );

                return;
            }
        }

        $extension = match ($detectedMimeType) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $originalFilename = $this->safeOriginalFilename($extension);
        $filename = $this->storedFilename($extension);
        $localPath = 'receipts/'.$filename;

        if (! Storage::put($localPath, $binaryData)) {
            throw new RuntimeException('Unable to store WhatsApp receipt media.');
        }

        try {
            $invoice = Invoice::create([
                'merchant_name' => 'Pending AI Extraction...',
                'date_time' => now(),
                'subtotal' => 0.00,
                'total_tax' => 0.00,
                'total_amount' => 0.00,
                'currency' => 'MYR',
                'source' => 'whatsapp',
                'whatsapp_sender' => $this->senderNumber,
                'whatsapp_message_id' => $this->messageId,
                'family_member_id' => InvoiceSenderAttribution::familyMemberIdForSender($this->senderNumber),
                'status' => 'pending',
                'image_path' => $localPath,
                'original_filename' => $originalFilename,
                'file_mime_type' => $detectedMimeType,
                'file_page_count' => $pageCount,
            ]);
        } catch (Throwable $exception) {
            Storage::delete($localPath);

            throw $exception;
        }

        WhatsAppDocumentReceivedDebouncer::register($this->senderNumber, [
            'message_id' => $this->messageId,
            'invoice_id' => $invoice->id,
            'filename' => $originalFilename,
            'mime_type' => $detectedMimeType,
            'page_count' => $pageCount,
            'status' => 'accepted',
            'reason' => null,
        ]);

        Log::info('WhatsApp receipt media processed', [
            'invoice_id' => $invoice->id,
            'message_id' => $this->messageId,
        ]);
    }

    public function failed(Throwable $exception): void
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

    protected function messageAlreadyHandled(): bool
    {
        if (Cache::has($this->rejectedMessageCacheKey())) {
            return true;
        }

        if (Invoice::query()->where('whatsapp_message_id', $this->messageId)->exists()) {
            return true;
        }

        $expectedExtension = $this->mediaType === 'pdf' ? 'pdf' : 'jpg';

        return Storage::exists('receipts/'.$this->storedFilename($expectedExtension));
    }

    protected function safeOriginalFilename(?string $fallbackExtension = null): string
    {
        $fallback = $this->storedFilename(
            $fallbackExtension ?? ($this->mediaType === 'pdf' ? 'pdf' : 'jpg'),
        );
        $filename = str_replace('\\', '/', trim((string) $this->originalFilename));
        $filename = basename($filename);

        return $filename !== '' ? Str::limit($filename, 255, '') : $fallback;
    }

    protected function storedFilename(string $extension): string
    {
        $safeMessageId = (string) Str::of($this->messageId)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '_')
            ->trim('_')
            ->limit(120, '');

        if ($safeMessageId === '') {
            $safeMessageId = hash('sha256', $this->messageId);
        }

        return 'wa_'.$safeMessageId.'.'.$extension;
    }

    protected function detectMimeType(string $binaryData): string
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binaryData);

        return is_string($mimeType) ? strtolower($mimeType) : 'application/octet-stream';
    }

    protected function registerRejectedDocument(
        string $filename,
        string $mimeType,
        string $reason,
        ?int $pageCount = null,
    ): void {
        WhatsAppDocumentReceivedDebouncer::register($this->senderNumber, [
            'message_id' => $this->messageId,
            'invoice_id' => null,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'page_count' => $pageCount,
            'status' => 'rejected',
            'reason' => $reason,
        ]);

        Cache::put($this->rejectedMessageCacheKey(), true, now()->addDays(7));

        Log::info('WhatsApp PDF rejected before AI parsing', [
            'message_id' => $this->messageId,
            'sender' => $this->senderNumber,
            'filename' => $filename,
            'page_count' => $pageCount,
            'reason' => $reason,
        ]);
    }

    protected function rejectedMessageCacheKey(): string
    {
        return 'wa:rejected-media:'.$this->messageId;
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
