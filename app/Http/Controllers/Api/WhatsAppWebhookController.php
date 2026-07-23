<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\MoneyDisplay;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessManualWhatsAppInvoiceJob;
use App\Jobs\ProcessWhatsAppMediaJob;
use App\Models\Invoice;
use App\Services\WhatsAppNotificationService;
use App\Support\ManualWhatsAppInvoiceParser;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, WhatsAppNotificationService $waService): JsonResponse
    {
        $token = $request->header('Authorization') ?? $request->query('token');
        $expectedToken = (string) config('services.evolution.api_key');

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

        $senderJid = $key['remoteJid'] ?? '';
        $senderNumber = explode('@', (string) $senderJid)[0] ?? '';

        if ($senderNumber === '') {
            return response()->json(['error' => 'No sender JID found'], 400);
        }

        if (! $this->isAllowedSender($senderNumber)) {
            Log::info('WhatsApp webhook ignored non-allowlisted sender', [
                'sender' => $senderNumber,
            ]);

            return response()->json(['status' => 'ignored_sender']);
        }

        // Self-chat ("Message yourself") often arrives as fromMe=true with remoteJid = your number.
        // Allowlisted senders are processed either way; strangers never reach here.

        $messageType = $data['messageType'] ?? '';

        if ($messageType === 'imageMessage') {
            return $this->handleImageMessage($data, $senderNumber);
        }

        if ($messageType === 'conversation' || $messageType === 'extendedTextMessage') {
            $text = $message['conversation'] ?? ($message['extendedTextMessage']['text'] ?? '');

            return $this->handleTextMessage($text, $senderNumber, $waService);
        }

        return response()->json(['status' => 'ignored_type']);
    }

    /**
     * Profile WhatsApp numbers plus allowlisted Family Members may trigger
     * bot replies / receipt import. Panel/OTP stay on the Profile phone only.
     */
    protected function isAllowedSender(string $senderNumber): bool
    {
        return PhoneNumber::isAllowedWhatsAppSender($senderNumber);
    }

    protected function handleImageMessage(array $data, string $senderNumber): JsonResponse
    {
        $key = $data['key'] ?? [];
        $messageId = (string) ($key['id'] ?? uniqid());
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        $fromMe = (bool) ($key['fromMe'] ?? false);

        ProcessWhatsAppMediaJob::dispatch(
            $senderNumber,
            $remoteJid,
            $messageId,
            $fromMe,
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

        $text = strtolower($originalText);

        if (str_contains($text, 'spend') || str_contains($text, 'total')) {
            $now = now();
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfMonth();

            $total = Invoice::whereBetween('date_time', [$start, $end])
                ->whereIn('status', ['parsed', 'reviewed'])
                ->sum('total_amount');

            $reply = WhatsAppMessage::compose(
                '💰',
                'Monthly spending',
                sprintf(
                    "Period: *%s*\n\nTotal spent: *RM %s*",
                    $now->format('F Y'),
                    MoneyDisplay::format((float) $total),
                ),
            );
            $waService->sendMessage($senderNumber, $reply);

            return response()->json(['status' => 'success', 'reply' => $reply]);
        }

        if (str_contains($text, 'manual way')) {
            $reply = WhatsAppMessage::manualApproach();
            $waService->sendMessage($senderNumber, $reply);

            return response()->json(['status' => 'success', 'reply' => $reply]);
        }

        $help = WhatsAppMessage::help();

        $waService->sendMessage($senderNumber, $help);

        return response()->json(['status' => 'success', 'reply' => $help]);
    }
}
