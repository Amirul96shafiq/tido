<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;
use App\Services\Ollama\OllamaSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaProbe implements ServiceHealthProbe
{
    public function __construct(
        private readonly OllamaSettings $settings,
    ) {}

    public function service(): MonitoredService
    {
        return MonitoredService::Ollama;
    }

    public function probe(): ServiceHealthResult
    {
        $host = $this->settings->host();
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(5)
                ->get("{$host}/api/tags")
                ->throw();

            $models = data_get($response->json(), 'models');

            if (! is_array($models)) {
                return new ServiceHealthResult(
                    status: ServiceHealthStatus::Degraded,
                    latencyMs: $this->elapsedMs($startedAt),
                    meta: ['message' => 'Ollama responded but model list was unexpected.'],
                );
            }

            $names = collect($models)
                ->map(fn (mixed $model): string => trim((string) data_get($model, 'name', '')))
                ->filter()
                ->values()
                ->all();

            $message = $names === []
                ? '0 model(s) available.'
                : count($names).' model(s) available: '.implode(', ', $names).'.';

            return new ServiceHealthResult(
                status: ServiceHealthStatus::Operational,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => $message],
            );
        } catch (Throwable $throwable) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Down,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Ollama is unreachable: '.$throwable->getMessage()],
            );
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
