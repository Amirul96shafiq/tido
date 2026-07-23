<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseProbe implements ServiceHealthProbe
{
    public function service(): MonitoredService
    {
        return MonitoredService::Database;
    }

    public function probe(): ServiceHealthResult
    {
        $startedAt = microtime(true);

        try {
            DB::connection()->getPdo();
            DB::select('select 1 as ok');

            return new ServiceHealthResult(
                status: ServiceHealthStatus::Operational,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Database connection is healthy.'],
            );
        } catch (Throwable $throwable) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Down,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Database connection failed: '.$throwable->getMessage()],
            );
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
