<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\MonitoredService;

interface ServiceHealthProbe
{
    public function service(): MonitoredService;

    public function probe(): ServiceHealthResult;
}
