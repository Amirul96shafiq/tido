<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Ollama\OllamaSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    public function __construct(
        private readonly OllamaSettings $settings,
    ) {}

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
            $host = $this->settings->host();
            $model = $this->settings->model();
            $timeout = $this->settings->timeout();
            $contextWindow = $this->settings->numCtx();

            $payload = [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
                'options' => [
                    'num_ctx' => $contextWindow,
                ],
            ];

            if ($images !== null && $images !== []) {
                $payload['images'] = $images;
            }

            $response = Http::timeout($timeout)
                ->post("{$host}/api/generate", $payload);

            if ($response->failed()) {
                Log::error('Ollama generate JSON HTTP request failed', [
                    'status' => $response->status(),
                    'has_images' => isset($payload['images']),
                ]);

                return null;
            }

            $responseBody = $response->json();
            $rawText = $responseBody['response'] ?? '';

            if (empty($rawText)) {
                Log::error('Ollama response text is empty', [
                    'has_images' => isset($payload['images']),
                ]);

                return null;
            }

            if (($responseBody['done_reason'] ?? null) === 'length') {
                Log::error('Ollama hit its token limit before finishing the JSON; context window exhausted', [
                    'model' => $model,
                    'num_ctx' => $contextWindow,
                    'prompt_eval_count' => $responseBody['prompt_eval_count'] ?? null,
                    'eval_count' => $responseBody['eval_count'] ?? null,
                    'response_chars' => strlen((string) $rawText),
                    'hint' => 'Lower max_image_dimension or raise num_ctx in Ollama settings.',
                ]);

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
                'json_error' => json_last_error_msg(),
                'raw_text_length' => strlen($text),
                'cleaned_text_length' => strlen($cleaned),
            ]);

            return null;
        }

        return $decoded;
    }
}
