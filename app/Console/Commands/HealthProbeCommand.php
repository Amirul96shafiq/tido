<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Health\ServiceHealthRecorder;
use Illuminate\Console\Command;

class HealthProbeCommand extends Command
{
    protected $signature = 'health:probe';

    protected $description = 'Probe monitored services and store health samples';

    public function handle(ServiceHealthRecorder $recorder): int
    {
        $samples = $recorder->recordAll();

        $this->info('Recorded '.count($samples).' health sample(s).');

        return self::SUCCESS;
    }
}
