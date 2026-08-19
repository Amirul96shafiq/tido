<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Models\ServiceHealthSample;
use App\Services\Health\ServiceHealthRecorder;
use App\Services\Health\ServiceHealthResult;

final class IntegrationHealthBadge
{
    public const LABEL = 'Active';

    private const REQUEST_CACHE_KEY = 'tido.integrationHealthLatest';

    /**
     * @var list<MonitoredService>
     */
    private const SERVICES = [
        MonitoredService::Ollama,
        MonitoredService::Evolution,
    ];

    public static function label(MonitoredService $service): ?string
    {
        return self::isOperational($service) ? self::LABEL : null;
    }

    public static function color(MonitoredService $service): ?string
    {
        return self::isOperational($service) ? 'success' : null;
    }

    public static function isOperational(MonitoredService $service): bool
    {
        return (self::latestStatuses()[$service->value] ?? null) === ServiceHealthStatus::Operational;
    }

    public static function forget(): void
    {
        request()->attributes->remove(self::REQUEST_CACHE_KEY);
    }

    public static function syncFromLiveStatus(
        MonitoredService $service,
        string $connectionStatus,
        ?int $latencyMs,
        string $message,
    ): bool {
        if ($connectionStatus === 'unknown') {
            return false;
        }

        $status = self::statusFromConnection($connectionStatus);
        $latest = ServiceHealthSample::query()
            ->where('service', $service)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();

        if ($latest?->status === $status) {
            return false;
        }

        app(ServiceHealthRecorder::class)->recordResult(
            $service,
            new ServiceHealthResult(
                status: $status,
                latencyMs: $latencyMs,
                meta: ['message' => $message],
            ),
        );
        self::forget();

        return true;
    }

    public static function statusFromConnection(string $connectionStatus): ServiceHealthStatus
    {
        $status = strtolower($connectionStatus);

        return match (true) {
            in_array($status, ['operational', 'open', 'connected'], true) => ServiceHealthStatus::Operational,
            in_array($status, ['degraded', 'connecting', 'close', 'closed', 'unconfigured'], true) => ServiceHealthStatus::Degraded,
            default => ServiceHealthStatus::Down,
        };
    }

    /**
     * @return array<string, ServiceHealthStatus>
     */
    private static function latestStatuses(): array
    {
        $cached = request()->attributes->get(self::REQUEST_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $serviceValues = array_map(
            static fn (MonitoredService $service): string => $service->value,
            self::SERVICES,
        );

        $samples = ServiceHealthSample::query()
            ->whereIn('service', $serviceValues)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get(['id', 'service', 'status', 'checked_at']);

        $latest = [];

        foreach ($samples as $sample) {
            $key = $sample->service->value;

            if (isset($latest[$key])) {
                continue;
            }

            $latest[$key] = $sample->status;
        }

        request()->attributes->set(self::REQUEST_CACHE_KEY, $latest);

        return $latest;
    }
}
