<?php

declare(strict_types=1);

namespace App\Services\Health\Probes;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Services\Health\ServiceHealthProbe;
use App\Services\Health\ServiceHealthResult;

class AppProbe implements ServiceHealthProbe
{
    public function service(): MonitoredService
    {
        return MonitoredService::App;
    }

    public function probe(): ServiceHealthResult
    {
        return new ServiceHealthResult(
            status: ServiceHealthStatus::Operational,
            latencyMs: 0,
            meta: ['message' => 'Application process is running.'],
        );
    }
}
