<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        if ($key['fromMe'] ?? false) {
            return response()->json(['status' => 'ignored_self']);
        }

        $senderJid = $key['remoteJid'] ?? '';
        $senderNumber = explode('@', $senderJid)[0] ?? '';

        if (empty($senderNumber)) {
            return response()->json(['error' => 'No sender JID found'], 400);
        }

        $messageType = $data['messageType'] ?? '';

        if ($messageType === 'imageMessage') {
            return $this->handleImageMessage($data, $senderNumber, $waService);
        }

        if ($messageType === 'conversation' || $messageType === 'extendedTextMessage') {
            $text = $message['conversation'] ?? ($message['extendedTextMessage']['text'] ?? '');

            return $this->handleTextMessage($text, $senderNumber, $waService);
        }

        return response()->json(['status' => 'ignored_type']);
    }

    protected function handleImageMessage(array $data, string $senderNumber, WhatsAppNotificationService $waService): JsonResponse
    {
        try {
            $instanceName = (string) config('services.evolution.instance_name');
            $apiUrl = rtrim((string) config('services.evolution.api_url'), '/');
            $apiKey = (string) config('services.evolution.api_key');

            $messageId = $data['key']['id'] ?? uniqid();

            $response = Http::withHeaders(['apikey' => $apiKey])
                ->post("{$apiUrl}/chat/retreiveMedia/{$instanceName}", [
                    'messageKeys' => [
                        'key' => [
                            'remoteJid' => $data['key']['remoteJid'],
                            'fromMe' => false,
                            'id' => $messageId,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Failed to retrieve media from Evolution API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $waService->sendMessage($senderNumber, '❌ Failed to download receipt image from WhatsApp. Please try again.');

                return response()->json(['error' => 'Failed to download media'], 500);
            }

            $body = $response->json();
            $base64Data = $body['base64'] ?? '';

            if (empty($base64Data)) {
                Log::error('Evolution API media response did not contain base64', ['response' => $body]);

                return response()->json(['error' => 'Empty media response'], 500);
            }

            if (strpos($base64Data, ',') !== false) {
                $base64Data = explode(',', $base64Data)[1];
            }

            $binaryData = base64_decode($base64Data);
            $filename = 'wa_'.$messageId.'.jpg';
            $localPath = 'receipts/'.$filename;

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

            $waService->sendMessage($senderNumber, "📥 Receipt received! AI is currently parsing it. We'll send you an update shortly.");

            return response()->json(['status' => 'success', 'invoice_id' => $invoice->id]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp Image Webhook handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function handleTextMessage(string $text, string $senderNumber, WhatsAppNotificationService $waService): JsonResponse
    {
        $text = strtolower(trim($text));

        if (str_contains($text, 'spend') || str_contains($text, 'total')) {
            $now = now();
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfMonth();

            $total = Invoice::whereBetween('date_time', [$start, $end])
                ->whereIn('status', ['parsed', 'reviewed'])
                ->sum('total_amount');

            $reply = sprintf('💰 Your total spending for this month (%s) is *RM %s*.', $now->format('F Y'), number_format($total, 2));
            $waService->sendMessage($senderNumber, $reply);

            return response()->json(['status' => 'success', 'reply' => $reply]);
        }

        $help = "🤖 *tido Bot Help*\n\n"
              ."- Send any *receipt image* to upload it.\n"
              .'- Type *spend* or *total* to view your total expenses for this month.';

        $waService->sendMessage($senderNumber, $help);

        return response()->json(['status' => 'success', 'reply' => $help]);
    }
}
