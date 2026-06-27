<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $instanceName;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('services.evolution.api_url'), '/');
        $this->apiKey = (string) config('services.evolution.api_key');
        $this->instanceName = (string) config('services.evolution.instance_name');
    }

    public function sendMessage(string $number, string $text): bool
    {
        try {
            if (! str_contains($number, '@')) {
                $number = $number . '@s.whatsapp.net';
            }

            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->post("{$this->apiUrl}/message/sendText/{$this->instanceName}", [
                    'number' => $number,
                    'text' => $text,
                ]);

            if ($response->failed()) {
                Log::error('WhatsAppNotificationService send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsAppNotificationService send error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
