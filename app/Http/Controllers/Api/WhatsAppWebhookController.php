<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsAppWebhookRequest;
use App\Jobs\ProcessManualWhatsAppExpenseJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Jobs\ProcessWhatsAppTextReplyJob;
use App\Support\ManualWhatsAppExpenseParser;
use App\Support\WhatsAppJid;
use App\Support\WhatsAppLid;
use App\Support\WhatsAppTypingCoordinator;
use App\Support\WhatsAppWebhookIdempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WhatsAppWebhookController extends Controller
{
    public function handle(WhatsAppWebhookRequest $request): JsonResponse
    {
        // Bearer auth is enforced in WhatsAppWebhookRequest::authorize() before schema validation.
        $validated = $request->validated();
        $event = (string) ($validated['event'] ?? '');

        Log::info('WhatsApp Webhook received', ['event' => $event !== '' ? $event : 'unknown']);

        if ($event !== 'messages.upsert') {
            return response()->json(['status' => 'ignored_event']);
        }

        /** @var array<string, mixed> $data */
        $data = $validated['data'] ?? [];
        /** @var array<string, mixed> $message */
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        /** @var array<string, mixed> $key */
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];

        $senderJid = (string) ($key['remoteJid'] ?? '');
        $messageId = (string) ($key['id'] ?? '');

        if ($senderJid === '' || $messageId === '') {
            return response()->json(['error' => 'Invalid payload'], 422);
        }

        if (! WhatsAppWebhookIdempotency::claim($messageId)) {
            return response()->json(['status' => 'duplicate']);
        }

        $senderPhone = WhatsAppJid::resolveAllowlistedSenderPhone($senderJid);

        if ($senderPhone === null) {
            if (WhatsAppLid::isLidIdentifier($senderJid)) {
                WhatsAppLid::rememberUnlinked(
                    $senderJid,
                    isset($data['pushName']) && is_string($data['pushName']) ? $data['pushName'] : null,
                );
            }

            Log::info('WhatsApp webhook ignored non-allowlisted sender', [
                'sender' => explode('@', $senderJid)[0] ?: $senderJid,
            ]);

            return response()->json(['status' => 'ignored_sender']);
        }

        if ($this->senderThrottleExceeded($senderPhone)) {
            return response()->json(['error' => 'Too many requests. Try again later.'], 429);
        }

        // Self-chat ("Message yourself") often arrives as fromMe=true with remoteJid = your number.
        // Allowlisted senders are processed either way; strangers never reach here.

        $messageType = (string) ($data['messageType'] ?? '');

        if ($messageType === 'imageMessage') {
            $image = is_array($message['imageMessage'] ?? null) ? $message['imageMessage'] : [];

            return $this->handleMediaMessage(
                $data,
                $senderPhone,
                'image',
                (string) ($image['mimetype'] ?? 'image/jpeg'),
            );
        }

        if ($messageType === 'documentMessage') {
            $document = is_array($message['documentMessage'] ?? null) ? $message['documentMessage'] : [];
            $mimeType = strtolower(trim((string) ($document['mimetype'] ?? '')));

            if ($mimeType !== 'application/pdf') {
                return response()->json(['status' => 'ignored_document_type']);
            }

            $filename = str_replace('\\', '/', (string) (
                $document['fileName']
                ?? $document['title']
                ?? 'document.pdf'
            ));

            return $this->handleMediaMessage(
                $data,
                $senderPhone,
                'pdf',
                $mimeType,
                basename($filename),
            );
        }

        if ($messageType === 'conversation' || $messageType === 'extendedTextMessage') {
            $text = $message['conversation']
                ?? (is_array($message['extendedTextMessage'] ?? null)
                    ? ($message['extendedTextMessage']['text'] ?? '')
                    : '');

            return $this->handleTextMessage((string) $text, $senderPhone);
        }

        return response()->json(['status' => 'ignored_type']);
    }

    protected function senderThrottleExceeded(string $senderPhone): bool
    {
        $max = max(1, (int) config('services.evolution.webhook_per_sender_attempts_per_minute', 20));
        $key = 'whatsapp-webhook:sender:'.$senderPhone;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleMediaMessage(
        array $data,
        string $senderNumber,
        string $mediaType,
        string $mimeType,
        ?string $originalFilename = null,
    ): JsonResponse {
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];
        $messageId = (string) ($key['id'] ?? '');
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        $fromMe = (bool) ($key['fromMe'] ?? false);

        WhatsAppTypingCoordinator::startSenderTyping($senderNumber);

        ProcessWhatsAppMediaJob::dispatch(
            $senderNumber,
            $remoteJid,
            $messageId,
            $fromMe,
            $mediaType,
            $mimeType,
            $originalFilename,
        );

        return response()->json(['status' => 'accepted']);
    }

    protected function handleTextMessage(string $text, string $senderNumber): JsonResponse
    {
        $originalText = trim($text);

        if (ManualWhatsAppExpenseParser::looksLike($originalText)) {
            ProcessManualWhatsAppExpenseJob::dispatch($senderNumber, $originalText);

            return response()->json(['status' => 'accepted']);
        }

        ProcessWhatsAppTextReplyJob::dispatch($senderNumber, $originalText);

        return response()->json(['status' => 'accepted']);
    }
}
