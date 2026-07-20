<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;

    protected string $model;

    protected int $timeout;

    public function __construct()
    {
        $this->host = rtrim(config('services.ollama.host'), '/');
        $this->model = config('services.ollama.model');
        $this->timeout = (int) config('services.ollama.timeout');
    }

    public function parseReceipt(string $base64Image, string $prompt): ?array
    {
        return $this->generateJson($prompt, [$base64Image]);
    }

    /**
     * @param  list<string>|null  $images  Optional base64 images for vision models
     */
    public function generateJson(string $prompt, ?array $images = null): ?array
    {
        try {
            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ];

            if ($images !== null && $images !== []) {
                $payload['images'] = $images;
            }

            $response = Http::timeout($this->timeout)
                ->post("{$this->host}/api/generate", $payload);

            if ($response->failed()) {
                Log::error('Ollama generate JSON HTTP request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'has_images' => isset($payload['images']),
                ]);

                return null;
            }

            $responseBody = $response->json();
            $rawText = $responseBody['response'] ?? '';

            if (empty($rawText)) {
                Log::error('Ollama response text is empty', ['response' => $responseBody]);

                return null;
            }

            return $this->cleanAndDecodeJson($rawText);
        } catch (\Throwable $e) {
            Log::error('Ollama service generate JSON error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function cleanAndDecodeJson(string $text): ?array
    {
        $cleaned = preg_replace('/^```(?:json)?\s+/i', '', trim($text));
        $cleaned = preg_replace('/\s+```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Ollama JSON decoding failed', [
                'raw_text' => $text,
                'cleaned_text' => $cleaned,
                'json_error' => json_last_error_msg(),
            ]);

            return null;
        }

        return $decoded;
    }
}
