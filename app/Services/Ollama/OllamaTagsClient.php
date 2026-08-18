<?php

declare(strict_types=1);

namespace App\Services\Ollama;

use Illuminate\Support\Facades\Http;
use Throwable;

final class OllamaTagsClient
{
    public function __construct(
        private readonly OllamaSettings $settings,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     latencyMs: int,
     *     models: list<array{
     *         name: string,
     *         family: string,
     *         parameterSize: string,
     *         quantization: string,
     *         contextLength: int,
     *         sizeBytes: int,
     *     }>,
     *     message: string,
     * }
     */
    public function fetch(?string $host = null, ?string $activeModel = null): array
    {
        $host = rtrim($host ?? $this->settings->host(), '/');
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(5)->get("{$host}/api/tags");
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'latencyMs' => $latencyMs,
                    'models' => [],
                    'message' => 'Ollama responded with HTTP '.$response->status().'.',
                ];
            }

            $rawModels = data_get($response->json(), 'models');

            if (! is_array($rawModels)) {
                return [
                    'success' => false,
                    'latencyMs' => $latencyMs,
                    'models' => [],
                    'message' => 'Ollama responded but the model list was unexpected.',
                ];
            }

            $models = collect($rawModels)
                ->map(fn (mixed $model): array => [
                    'name' => (string) data_get($model, 'name', ''),
                    'family' => (string) data_get($model, 'details.family', '—'),
                    'parameterSize' => (string) data_get($model, 'details.parameter_size', '—'),
                    'quantization' => (string) data_get($model, 'details.quantization_level', '—'),
                    'contextLength' => (int) data_get($model, 'details.context_length', 0),
                    'sizeBytes' => (int) data_get($model, 'size', 0),
                    'isConfigured' => $activeModel !== null && data_get($model, 'name') === $activeModel,
                ])
                ->filter(fn (array $model): bool => $model['name'] !== '')
                ->values()
                ->all();

            return [
                'success' => true,
                'latencyMs' => $latencyMs,
                'models' => $models,
                'message' => count($models) === 0
                    ? 'Ollama is reachable but no models are installed.'
                    : 'Connected.',
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'latencyMs' => (int) round((microtime(true) - $startedAt) * 1000),
                'models' => [],
                'message' => 'Ollama is unreachable: '.$throwable->getMessage(),
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function modelNames(?string $host = null): array
    {
        $result = $this->fetch($host);

        return collect($result['models'])
            ->pluck('name')
            ->values()
            ->all();
    }
}
