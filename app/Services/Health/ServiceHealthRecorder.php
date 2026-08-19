<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\MonitoredService;
use App\Models\ServiceHealthSample;
use App\Services\Health\Probes\AppProbe;
use App\Services\Health\Probes\DatabaseProbe;
use App\Services\Health\Probes\EvolutionProbe;
use App\Services\Health\Probes\OllamaProbe;
use App\Services\Health\Probes\QueueProbe;
use App\Services\Health\Probes\ReverbProbe;

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
        ReverbProbe $reverbProbe,
        private readonly ServiceHealthAlertService $alertService,
    ) {
        $this->probes = [
            $appProbe,
            $databaseProbe,
            $ollamaProbe,
            $evolutionProbe,
            $queueProbe,
            $reverbProbe,
        ];
    }

    /**
     * @return list<ServiceHealthSample>
     */
    public function recordAll(): array
    {
        $previousByService = $this->latestSamplesByService();
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

        $this->alertService->notifyTransitions($previousByService, $samples);

        return $samples;
    }

    public function recordResult(MonitoredService $service, ServiceHealthResult $result): ServiceHealthSample
    {
        $previousByService = $this->latestSamplesByService();

        $sample = ServiceHealthSample::query()->create([
            'service' => $service,
            'status' => $result->status,
            'checked_at' => now(),
            'latency_ms' => $result->latencyMs,
            'meta' => $result->meta,
        ]);

        $this->alertService->notifyTransitions($previousByService, [$sample]);

        return $sample;
    }

    /**
     * @return array<string, ServiceHealthSample>
     */
    private function latestSamplesByService(): array
    {
        $latest = [];

        foreach (MonitoredService::configured() as $service) {
            $sample = ServiceHealthSample::query()
                ->where('service', $service)
                ->latest('checked_at')
                ->latest('id')
                ->first();

            if ($sample instanceof ServiceHealthSample) {
                $latest[$service->value] = $sample;
            }
        }

        return $latest;
    }

    public function pruneOlderThanDays(int $days = 30): int
    {
        return ServiceHealthSample::query()
            ->where('checked_at', '<', now()->subDays($days))
            ->delete();
    }
}
