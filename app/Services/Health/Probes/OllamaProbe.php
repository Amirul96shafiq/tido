<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaProbe implements ServiceHealthProbe
{
    public function service(): MonitoredService
    {
        return MonitoredService::Ollama;
    }

    public function probe(): ServiceHealthResult
    {
        $host = rtrim((string) config('services.ollama.host'), '/');
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

            return new ServiceHealthResult(
                status: ServiceHealthStatus::Operational,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => count($models).' model(s) available.'],
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
