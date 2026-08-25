<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Expense;
use App\Services\PdfInspectionException;
use App\Services\PdfPageInspector;
use App\Services\WhatsAppNotificationService;
use App\Support\EvolutionCredential;
use App\Support\ExpenseSenderAttribution;
use App\Support\ReceiptPipelineLogger;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppProcessingJobKey;
use App\Support\WhatsAppTypingCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessWhatsAppMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public string $senderNumber,
        public string $remoteJid,
        public string $messageId,
        public bool $fromMe,
        public string $mediaType = 'image',
        public ?string $declaredMimeType = null,
        public ?string $originalFilename = null,
    ) {
        $this->onQueue('whatsapp');
        $this->timeout = max(1, (int) config('services.evolution.timeout', 15))
            + max(1, (int) config('services.evolution.connect_timeout', 5))
            + 120;
        $this->uniqueFor = WhatsAppProcessingJobKey::uniqueForSeconds();
    }

    public function uniqueId(): string
    {
        return WhatsAppProcessingJobKey::forMessage($this->messageId, 'media');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->expireAfter($this->timeout + 60)
                ->releaseAfter(10),
            new RateLimited('evolution-send'),
        ];
    }

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
        $startedAt = ReceiptPipelineLogger::start();

        if ($this->messageAlreadyHandled()) {
            Log::info('WhatsApp media job skipped duplicate message', [
                'message_id' => $this->messageId,
            ]);

            ReceiptPipelineLogger::completed('receipt.media.processed', $startedAt, [
                'message_id' => $this->messageId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'duplicate',
            ]);

            return;
        }

        WhatsAppTypingCoordinator::startSenderTyping($this->senderNumber);

        $binaryData = $this->downloadMedia();

        if ($binaryData === null) {
            $attempt = $this->attemptNumber();

            $waService->sendMessage(
                $this->senderNumber,
                WhatsAppMessage::receiptUploadFailed($attempt, $this->tries),
                messageId: $this->messageId,
            );

            WhatsAppTypingCoordinator::stopSenderTyping($this->senderNumber);

            ReceiptPipelineLogger::completed('receipt.media.processed', $startedAt, [
                'message_id' => $this->messageId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'failed',
                'reason' => 'media_download_failed',
            ]);

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

            WhatsAppTypingCoordinator::stopSenderTyping($this->senderNumber);

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
            WhatsAppTypingCoordinator::stopSenderTyping($this->senderNumber);

            throw new RuntimeException('Unsupported WhatsApp receipt image type.');
        }

        $pageCount = null;

        if ($detectedMimeType === 'application/pdf') {
            $inspectionStartedAt = ReceiptPipelineLogger::start();

            try {
                $pageCount = $pdfPageInspector->pageCount($binaryData);

                ReceiptPipelineLogger::completed('receipt.pdf.inspect', $inspectionStartedAt, [
                    'message_id' => $this->messageId,
                    'queue' => $this->queue ?? 'default',
                    'outcome' => 'success',
                    'page_count' => $pageCount,
                ]);
            } catch (PdfInspectionException $exception) {
                ReceiptPipelineLogger::completed('receipt.pdf.inspect', $inspectionStartedAt, [
                    'message_id' => $this->messageId,
                    'queue' => $this->queue ?? 'default',
                    'outcome' => $exception->reason === PdfInspectionException::DEPENDENCY_MISSING
                        ? 'deferred'
                        : 'rejected',
                    'reason' => $exception->reason,
                ]);

                if ($exception->reason === PdfInspectionException::DEPENDENCY_MISSING) {
                    Log::warning('WhatsApp PDF page inspection deferred until AI parsing', [
                        'message_id' => $this->messageId,
                        'filename' => $originalFilename,
                        'error' => $exception->getMessage(),
                    ]);
                } else {
                    $this->registerRejectedDocument(
                        filename: $originalFilename,
                        mimeType: $detectedMimeType,
                        reason: $exception->reason,
                    );

                    return;
                }
            }

            $maximumPages = max(1, (int) config('services.documents.max_pdf_pages', 4));

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

        $storageStartedAt = ReceiptPipelineLogger::start();

        if (! Storage::put($localPath, $binaryData)) {
            WhatsAppTypingCoordinator::stopSenderTyping($this->senderNumber);

            ReceiptPipelineLogger::completed('receipt.media.store', $storageStartedAt, [
                'message_id' => $this->messageId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'failed',
            ]);

            throw new RuntimeException('Unable to store WhatsApp receipt media.');
        }

        ReceiptPipelineLogger::completed('receipt.media.store', $storageStartedAt, [
            'message_id' => $this->messageId,
            'queue' => $this->queue ?? 'default',
            'outcome' => 'success',
        ]);

        $persistStartedAt = ReceiptPipelineLogger::start();

        try {
            $expense = Expense::create([
                'merchant_name' => 'Pending AI Extraction...',
                'date_time' => now(),
                'subtotal' => 0.00,
                'total_tax' => 0.00,
                'total_amount' => 0.00,
                'currency' => 'MYR',
                'source' => 'whatsapp',
                'whatsapp_sender' => $this->senderNumber,
                'whatsapp_message_id' => $this->messageId,
                'family_member_id' => ExpenseSenderAttribution::familyMemberIdForSender($this->senderNumber),
                'status' => 'pending',
                'image_path' => $localPath,
                'original_filename' => $originalFilename,
                'file_mime_type' => $detectedMimeType,
                'file_page_count' => $pageCount,
            ]);

            ReceiptPipelineLogger::completed('receipt.media.persist', $persistStartedAt, [
                'message_id' => $this->messageId,
                'expense_id' => $expense->id,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'success',
            ]);
        } catch (Throwable $exception) {
            Storage::delete($localPath);
            WhatsAppTypingCoordinator::stopSenderTyping($this->senderNumber);

            ReceiptPipelineLogger::completed('receipt.media.persist', $persistStartedAt, [
                'message_id' => $this->messageId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'failed',
            ]);

            throw $exception;
        }

        WhatsAppTypingCoordinator::handoffSenderToExpense($expense->id, $this->senderNumber);

        WhatsAppDocumentReceivedDebouncer::register($this->senderNumber, [
            'message_id' => $this->messageId,
            'expense_id' => $expense->id,
            'filename' => $originalFilename,
            'mime_type' => $detectedMimeType,
            'page_count' => $pageCount,
            'status' => 'accepted',
            'reason' => null,
        ]);

        Log::info('WhatsApp receipt media processed', [
            'expense_id' => $expense->id,
            'message_id' => $this->messageId,
        ]);

        ReceiptPipelineLogger::completed('receipt.media.processed', $startedAt, [
            'message_id' => $this->messageId,
            'expense_id' => $expense->id,
            'queue' => $this->queue ?? 'default',
            'outcome' => 'success',
        ]);
    }

    public function failed(Throwable $exception): void
    {
        if ($this->mediaType === 'pdf' && $this->isDatabaseQueueReservationFailure($exception)) {
            try {
                $this->registerProcessingFailure();
            } catch (Throwable $fallbackException) {
                Log::error('Unable to register WhatsApp PDF processing failure for acknowledgement', [
                    'message_id' => $this->messageId,
                    'error' => $fallbackException->getMessage(),
                ]);

                $this->deliverFallbackAcknowledgement($exception);
            }
        }

        WhatsAppTypingCoordinator::stopSenderTyping($this->senderNumber);

        Log::error('ProcessWhatsAppMediaJob failed after maximum retries', [
            'message_id' => $this->messageId,
            'sender' => $this->senderNumber,
            'error' => $exception->getMessage(),
        ]);

        ReceiptPipelineLogger::event('receipt.media.processed', [
            'message_id' => $this->messageId,
            'queue' => $this->queue ?? 'default',
            'outcome' => 'failed',
            'reason' => 'maximum_retries',
        ]);
    }

    protected function isDatabaseQueueReservationFailure(Throwable $exception): bool
    {
        do {
            $message = strtolower($exception->getMessage());

            if (
                str_contains($message, 'database is locked')
                && str_contains($message, 'reserved_at')
            ) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return false;
    }

    protected function deliverFallbackAcknowledgement(Throwable $exception): void
    {
        $payload = Cache::get(WhatsAppDocumentReceivedDebouncer::cacheKey($this->senderNumber));
        $token = is_array($payload) ? ($payload['token'] ?? null) : null;

        if (! is_string($token) || trim($token) === '') {
            return;
        }

        (new SendWhatsAppDocumentReceivedAckJob($this->senderNumber, $token))
            ->failed($exception);
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

        if (Expense::query()->where('whatsapp_message_id', $this->messageId)->exists()) {
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
        $this->registerDocumentOutcome(
            filename: $filename,
            mimeType: $mimeType,
            status: 'rejected',
            reason: $reason,
            pageCount: $pageCount,
        );
    }

    protected function registerProcessingFailure(): void
    {
        $this->registerDocumentOutcome(
            filename: $this->safeOriginalFilename('pdf'),
            mimeType: 'application/pdf',
            status: 'failed',
            reason: 'pdf_processing_failed',
        );
    }

    protected function registerDocumentOutcome(
        string $filename,
        string $mimeType,
        string $status,
        string $reason,
        ?int $pageCount = null,
    ): void {
        WhatsAppTypingCoordinator::stopSenderTyping($this->senderNumber);

        WhatsAppDocumentReceivedDebouncer::register($this->senderNumber, [
            'message_id' => $this->messageId,
            'expense_id' => null,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'page_count' => $pageCount,
            'status' => $status,
            'reason' => $reason,
        ]);

        Cache::put($this->rejectedMessageCacheKey(), true, now()->addDays(7));

        ReceiptPipelineLogger::event('receipt.media.outcome', [
            'message_id' => $this->messageId,
            'queue' => $this->queue ?? 'default',
            'outcome' => $status,
            'reason' => $reason,
        ]);

        Log::info($status === 'failed'
            ? 'WhatsApp PDF processing failure registered for acknowledgement'
            : 'WhatsApp PDF rejected before AI parsing', [
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
        $startedAt = ReceiptPipelineLogger::start();
        $instanceName = (string) config('services.evolution.instance_name');
        $apiUrl = rtrim((string) config('services.evolution.api_url'), '/');
        $apiKey = (string) config('services.evolution.api_key');

        if ($apiUrl === '' || ! EvolutionCredential::isValid($apiKey)) {
            Log::error('Failed to retrieve media from Evolution API because the API credential is not configured', [
                'message_id' => $this->messageId,
            ]);

            ReceiptPipelineLogger::completed('receipt.media.download', $startedAt, [
                'message_id' => $this->messageId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'failed',
                'reason' => 'evolution_not_configured',
            ]);

            return null;
        }

        try {
            $timeout = max(1, (int) config('services.evolution.timeout', 15));
            $connectTimeout = max(1, (int) config('services.evolution.connect_timeout', 5));

            $response = Http::withHeaders(['apikey' => $apiKey])
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
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
                ]);

                ReceiptPipelineLogger::completed('receipt.media.download', $startedAt, [
                    'message_id' => $this->messageId,
                    'queue' => $this->queue ?? 'default',
                    'outcome' => 'failed',
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json();
            $base64Data = $body['base64'] ?? '';

            if ($base64Data === '') {
                Log::error('Evolution API media response did not contain base64', [
                    'message_id' => $this->messageId,
                ]);

                ReceiptPipelineLogger::completed('receipt.media.download', $startedAt, [
                    'message_id' => $this->messageId,
                    'queue' => $this->queue ?? 'default',
                    'outcome' => 'failed',
                    'reason' => 'missing_base64',
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

                ReceiptPipelineLogger::completed('receipt.media.download', $startedAt, [
                    'message_id' => $this->messageId,
                    'queue' => $this->queue ?? 'default',
                    'outcome' => 'failed',
                    'reason' => 'invalid_base64',
                ]);

                return null;
            }

            ReceiptPipelineLogger::completed('receipt.media.download', $startedAt, [
                'message_id' => $this->messageId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'success',
                'bytes' => strlen($binaryData),
            ]);

            return $binaryData;
        } catch (Throwable $exception) {
            ReceiptPipelineLogger::completed('receipt.media.download', $startedAt, [
                'message_id' => $this->messageId,
                'queue' => $this->queue ?? 'default',
                'outcome' => 'error',
            ]);

            throw $exception;
        }
    }
}
