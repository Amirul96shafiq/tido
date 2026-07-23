<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Health\ServiceHealthRecorder;
use Illuminate\Console\Command;

class HealthPruneCommand extends Command
{
    protected $signature = 'health:prune {--days=30 : Delete samples older than this many days}';

    protected $description = 'Prune stored service health samples older than the retention window';

    public function handle(ServiceHealthRecorder $recorder): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = $recorder->pruneOlderThanDays($days);

        $this->info("Pruned {$deleted} health sample(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
