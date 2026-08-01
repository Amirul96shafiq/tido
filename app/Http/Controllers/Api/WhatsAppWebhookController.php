<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessManualWhatsAppInvoiceJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Services\WhatsAppNotificationService;
use App\Support\ManualWhatsAppInvoiceParser;
use App\Support\PhoneNumber;
use App\Support\WhatsAppLid;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppSpendingCommandParser;
use App\Support\WhatsAppSpendingReplyBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, WhatsAppNotificationService $waService): JsonResponse
    {
        $token = $request->header('Authorization') ?? $request->query('token');
        $expectedToken = (string) config('services.evolution.api_key');

        // #region agent log
        $this->writeDebugLog('H1', 'Webhook authentication evaluated', [
            'has_authorization_header' => $request->hasHeader('Authorization'),
            'has_query_token' => $request->query('token') !== null,
            'expected_token_configured' => $expectedToken !== '',
            'authenticated' => $token === 'Bearer '.$expectedToken || $token === $expectedToken,
        ]);
        // #endregion

        if ($token !== 'Bearer '.$expectedToken && $token !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        Log::info('WhatsApp Webhook received', ['event' => $payload['event'] ?? 'unknown']);

        if (($payload['event'] ?? '') !== 'messages.upsert') {
            return response()->json(['status' => 'ignored_event']);
        }

        $data = $payload['data'] ?? [];
        $message = $data['message'] ?? [];
        $key = $data['key'] ?? [];

        // #region agent log
        $this->writeDebugLog('H2-H3', 'Webhook sender identity candidates received', [
            'event' => $payload['event'] ?? null,
            'message_type' => $data['messageType'] ?? null,
            'data_keys' => array_keys(is_array($data) ? $data : []),
            'key_keys' => array_keys(is_array($key) ? $key : []),
            'remote_jid' => $this->debugIdentifier((string) ($key['remoteJid'] ?? '')),
            'remote_jid_alt' => $this->debugIdentifier((string) ($key['remoteJidAlt'] ?? '')),
            'participant' => $this->debugIdentifier((string) ($key['participant'] ?? '')),
            'participant_alt' => $this->debugIdentifier((string) ($key['participantAlt'] ?? '')),
            'envelope_sender' => $this->debugIdentifier((string) ($payload['sender'] ?? '')),
        ]);
        // #endregion

        $senderJid = (string) ($key['remoteJid'] ?? '');

        if ($senderJid === '') {
            return response()->json(['error' => 'No sender JID found'], 400);
        }

        $senderPhone = PhoneNumber::resolveAllowlistedSenderPhone($senderJid);

        // #region agent log
        $this->writeDebugLog('H4-H5', 'Webhook sender gate evaluated', [
            'selected_sender' => $this->debugIdentifier($senderJid !== '' ? $senderJid : 'missing'),
            'resolved_phone' => $senderPhone !== null,
            'is_lid' => WhatsAppLid::isLidIdentifier($senderJid),
            'allowed' => $senderPhone !== null,
            'allowlist_count' => count(PhoneNumber::allowedWhatsAppSenders()),
            'message_type' => $data['messageType'] ?? null,
        ]);
        // #endregion

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

        // Self-chat ("Message yourself") often arrives as fromMe=true with remoteJid = your number.
        // Allowlisted senders are processed either way; strangers never reach here.

        $messageType = $data['messageType'] ?? '';

        if ($messageType === 'imageMessage') {
            $image = $message['imageMessage'] ?? [];

            return $this->handleMediaMessage(
                $data,
                $senderPhone,
                'image',
                (string) ($image['mimetype'] ?? 'image/jpeg'),
            );
        }

        if ($messageType === 'documentMessage') {
            $document = $message['documentMessage'] ?? [];
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
            $text = $message['conversation'] ?? ($message['extendedTextMessage']['text'] ?? '');

            return $this->handleTextMessage($text, $senderPhone, $waService);
        }

        return response()->json(['status' => 'ignored_type']);
    }

    /**
     * Profile WhatsApp numbers plus allowlisted Family Members may trigger
     * bot replies / receipt import. Panel OTP login uses users.phone for primary
     * and login-enabled Family Members. Linked WhatsApp LIDs resolve to those phones.
     */
    protected function isAllowedSender(string $senderNumber): bool
    {
        return PhoneNumber::isAllowedWhatsAppSender($senderNumber);
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
        $key = $data['key'] ?? [];
        $messageId = (string) ($key['id'] ?? uniqid());
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        $fromMe = (bool) ($key['fromMe'] ?? false);

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

    protected function handleTextMessage(string $text, string $senderNumber, WhatsAppNotificationService $waService): JsonResponse
    {
        $originalText = trim($text);

        if (ManualWhatsAppInvoiceParser::looksLike($originalText)) {
            ProcessManualWhatsAppInvoiceJob::dispatch($senderNumber, $originalText);

            return response()->json(['status' => 'accepted']);
        }

        $spendingCommand = WhatsAppSpendingCommandParser::parse($originalText);

        if ($spendingCommand !== null) {
            $reply = (new WhatsAppSpendingReplyBuilder(
                $spendingCommand['month'],
                $spendingCommand['mode'],
            ))->build();
            $waService->sendMessage($senderNumber, $reply);

            return response()->json(['status' => 'success', 'reply' => $reply]);
        }

        $text = strtolower($originalText);

        if (str_contains($text, 'finance others')) {
            $reply = WhatsAppMessage::financeKeywords();
            $waService->sendMessage($senderNumber, $reply);

            return response()->json(['status' => 'success', 'reply' => $reply]);
        }

        if (str_contains($text, 'manual way') || preg_match('/\bmanual\b/u', $text) === 1) {
            $reply = WhatsAppMessage::manualApproach();
            $waService->sendMessage($senderNumber, $reply);

            return response()->json(['status' => 'success', 'reply' => $reply]);
        }

        $help = WhatsAppMessage::help();

        $waService->sendMessage($senderNumber, $help);

        return response()->json(['status' => 'success', 'reply' => $help]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeDebugLog(string $hypothesisId, string $message, array $data): void
    {
        $entry = json_encode([
            'sessionId' => '48b926',
            'runId' => 'post-fix',
            'hypothesisId' => $hypothesisId,
            'location' => 'app/Http/Controllers/Api/WhatsAppWebhookController.php',
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) floor(microtime(true) * 1000),
        ], JSON_UNESCAPED_SLASHES);

        if (is_string($entry)) {
            file_put_contents(base_path('debug-48b926.log'), $entry.PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @return array{present: bool, kind: string|null, hash: string|null}
     */
    private function debugIdentifier(string $identifier): array
    {
        $trimmed = trim($identifier);

        if ($trimmed === '') {
            return ['present' => false, 'kind' => null, 'hash' => null];
        }

        $parts = explode('@', $trimmed, 2);

        return [
            'present' => true,
            'kind' => $parts[1] ?? 'number',
            'hash' => substr(hash('sha256', $trimmed), 0, 12),
        ];
    }
}
