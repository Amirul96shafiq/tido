<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\EvolutionInstanceService;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;

class EvolutionProbe implements ServiceHealthProbe
{
    public function __construct(
        private readonly EvolutionInstanceService $evolution,
    ) {}

    public function service(): MonitoredService
    {
        return MonitoredService::Evolution;
    }

    public function probe(): ServiceHealthResult
    {
        if (! $this->evolution->isConfigured()) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Degraded,
                meta: ['message' => 'Evolution API is not configured.'],
            );
        }

        $state = $this->evolution->connectionState();

        if (! $state['ok']) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Down,
                meta: ['message' => (string) ($state['message'] ?? 'Evolution API is unreachable.')],
            );
        }

        $status = strtolower((string) ($state['status'] ?? 'unknown'));

        if (in_array($status, ['open', 'connected'], true)) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Operational,
                meta: ['message' => 'WhatsApp session is connected.'],
            );
        }

        if (in_array($status, ['connecting', 'close', 'closed'], true)) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Degraded,
                meta: ['message' => 'Evolution API is reachable but WhatsApp is not connected ('.$status.').'],
            );
        }

        return new ServiceHealthResult(
            status: ServiceHealthStatus::Degraded,
            meta: ['message' => 'Evolution API returned status: '.$status.'.'],
        );
    }
}
