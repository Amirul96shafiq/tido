<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class QueueProbe implements ServiceHealthProbe
{
    private const FAILED_JOB_THRESHOLD = 25;

    public function service(): MonitoredService
    {
        return MonitoredService::Queue;
    }

    public function probe(): ServiceHealthResult
    {
        $startedAt = microtime(true);
        $driver = (string) config('queue.default');

        try {
            if ($driver === 'redis') {
                Redis::connection()->ping();
            } else {
                DB::connection(config('queue.connections.'.$driver.'.connection'))->getPdo();
            }

            $failedCount = (int) DB::table('failed_jobs')->count();

            if ($failedCount >= self::FAILED_JOB_THRESHOLD) {
                return new ServiceHealthResult(
                    status: ServiceHealthStatus::Degraded,
                    latencyMs: $this->elapsedMs($startedAt),
                    meta: ['message' => $failedCount.' failed jobs in the queue backlog.'],
                );
            }

            return new ServiceHealthResult(
                status: ServiceHealthStatus::Operational,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Queue driver ('.$driver.') is healthy.'],
            );
        } catch (Throwable $throwable) {
            return new ServiceHealthResult(
                status: ServiceHealthStatus::Down,
                latencyMs: $this->elapsedMs($startedAt),
                meta: ['message' => 'Queue health check failed: '.$throwable->getMessage()],
            );
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
