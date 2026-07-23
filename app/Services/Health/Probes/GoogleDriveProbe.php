<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GoogleDriveProbe implements ServiceHealthProbe
{
    public function service(): MonitoredService
    {
        return MonitoredService::GoogleDrive;
    }

    public function probe(): ServiceHealthResult
    {
        if (! MonitoredService::GoogleDrive->isConfigured()) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Unknown,
                meta: ['message' => 'Google Drive is not configured.'],
            );
        }

        $startedAt = microtime(true);

        try {
            Storage::disk('google')->files('/');

            return new ServiceHealthResult(
                status: ServiceHealthStatus::Operational,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Google Drive folder is reachable.'],
            );
        } catch (Throwable $throwable) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Down,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Google Drive check failed: '.$throwable->getMessage()],
            );
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
