<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Models\ServiceHealthSample;
use App\Services\Health\Probes\AppProbe;
use App\Services\Health\Probes\DatabaseProbe;
use App\Services\Health\Probes\EvolutionProbe;
use App\Services\Health\Probes\GoogleDriveProbe;
use App\Services\Health\Probes\OllamaProbe;
use App\Services\Health\Probes\QueueProbe;

class ServiceHealthRecorder
{
    /**
     * @var list<ServiceHealthProbe>
     */
    private array $probes;

    public function __construct(
        AppProbe $appProbe,
        DatabaseProbe $databaseProbe,
        OllamaProbe $ollamaProbe,
        EvolutionProbe $evolutionProbe,
        QueueProbe $queueProbe,
        GoogleDriveProbe $googleDriveProbe,
    ) {
        $this->probes = [
            $appProbe,
            $databaseProbe,
            $ollamaProbe,
            $evolutionProbe,
            $queueProbe,
            $googleDriveProbe,
        ];
    }

    /**
     * @return list<ServiceHealthSample>
     */
    public function recordAll(): array
    {
        $checkedAt = now();
        $samples = [];

        foreach ($this->probes as $probe) {
            if (! $probe->service()->isConfigured()) {
                continue;
            }

            $result = $probe->probe();

            $samples[] = ServiceHealthSample::query()->create([
                'service' => $probe->service(),
                'status' => $result->status,
                'checked_at' => $checkedAt,
                'latency_ms' => $result->latencyMs,
                'meta' => $result->meta,
            ]);
        }

        return $samples;
    }

    public function pruneOlderThanDays(int $days = 30): int
    {
        return ServiceHealthSample::query()
            ->where('checked_at', '<', now()->subDays($days))
            ->delete();
    }
}
