<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class ReverbProbe implements ServiceHealthProbe
{
    public function service(): MonitoredService
    {
        return MonitoredService::Reverb;
    }

    public function probe(): ServiceHealthResult
    {
        $url = $this->probeUrl();
        $startedAt = microtime(true);

        if ($url === null) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Degraded,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Reverb is not configured.'],
            );
        }

        try {
            Http::timeout(5)
                ->connectTimeout(3)
                ->get($url);

            return new ServiceHealthResult(
                status: ServiceHealthStatus::Operational,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Reverb websocket is reachable.'],
            );
        } catch (Throwable) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Down,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Reverb is unreachable.'],
            );
        }
    }

    private function probeUrl(): ?string
    {
        /** @var array<string, mixed> $options */
        $options = config('broadcasting.connections.reverb.options', []);
        $scheme = strtolower((string) ($options['scheme'] ?? 'http'));
        $host = trim((string) ($options['host'] ?? ''));
        $port = (int) ($options['port'] ?? 0);

        if ($host === '' || in_array($host, ['0.0.0.0', '::'], true)) {
            $host = '127.0.0.1';
        }

        if ($port < 1) {
            return null;
        }

        if (! in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'http';
        }

        return sprintf('%s://%s:%d/apps', $scheme, $host, $port);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
