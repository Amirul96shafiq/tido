<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Models\ServiceHealthSample;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ServiceHealthAggregator
{
    public const VISIBLE_DAYS = 30;

    public const PIECES_PER_DAY = 2;

    public const PIECE_HOURS = 12;

    /**
     * @return array{
     *     summary: array{
     *         status: ServiceHealthStatus,
     *         title: string,
     *         message: string,
     *     },
     *     periodDateRange: string,
     *     services: list<array{
     *         service: MonitoredService,
     *         label: string,
     *         currentStatus: ServiceHealthStatus,
     *         uptimePercent: float|null,
     *         uptimeLabel: string,
     *         pieces: list<array{
     *             startsAt: CarbonInterface,
     *             endsAt: CarbonInterface,
     *             status: ServiceHealthStatus,
     *             detail: string,
     *             tooltip: string,
     *             ariaLabel: string,
     *         }>,
     *     }>,
     * }
     */
    public function report(?string $timezone = null): array
    {
        $timezone ??= (string) config('app.timezone', 'UTC');
        $now = now($timezone);
        $windowStart = $now->copy()->subDays(self::VISIBLE_DAYS - 1)->startOfDay();
        $windowEnd = $now->copy();

        $samples = ServiceHealthSample::query()
            ->where('checked_at', '>=', $windowStart)
            ->where('checked_at', '<=', $windowEnd)
            ->orderBy('checked_at')
            ->get();

        $services = [];

        foreach (MonitoredService::configured() as $service) {
            $serviceSamples = $samples->filter(
                static fn (ServiceHealthSample $sample): bool => $sample->service === $service,
            );

            $services[] = $this->buildServiceReport($service, $serviceSamples, $timezone, $windowStart, $windowEnd);
        }

        usort(
            $services,
            static fn (array $left, array $right): int => $left['service']->sortOrder() <=> $right['service']->sortOrder(),
        );

        return [
            'summary' => $this->buildSummary($services),
            'periodDateRange' => $windowStart->format('j M Y').' – '.$windowEnd->format('j M Y'),
            'services' => $services,
        ];
    }

    /**
     * @param  Collection<int, ServiceHealthSample>  $samples
     * @return array{
     *     service: MonitoredService,
     *     label: string,
     *     currentStatus: ServiceHealthStatus,
     *     uptimePercent: float|null,
     *     uptimeLabel: string,
     *     pieces: list<array{
     *         startsAt: CarbonInterface,
     *         endsAt: CarbonInterface,
     *         status: ServiceHealthStatus,
     *         detail: string,
     *         tooltip: string,
     *         ariaLabel: string,
     *     }>,
     * }
     */
    private function buildServiceReport(
        MonitoredService $service,
        Collection $samples,
        string $timezone,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
    ): array {
        $pieces = [];
        $pieceCount = self::VISIBLE_DAYS * self::PIECES_PER_DAY;

        for ($index = 0; $index < $pieceCount; $index++) {
            $pieceStart = $windowStart->copy()->addHours($index * self::PIECE_HOURS);
            $pieceEnd = $pieceStart->copy()->addHours(self::PIECE_HOURS);

            $pieceSamples = $samples->filter(
                static function (ServiceHealthSample $sample) use ($pieceStart, $pieceEnd, $timezone): bool {
                    $checkedAt = $sample->checked_at?->copy()->timezone($timezone);

                    return $checkedAt !== null
                        && $checkedAt->greaterThanOrEqualTo($pieceStart)
                        && $checkedAt->lessThan($pieceEnd);
                },
            );

            $pieces[] = $this->buildPiece($pieceStart, $pieceEnd, $pieceSamples);
        }

        $latestSample = $samples->sortByDesc('checked_at')->first();
        $currentStatus = $latestSample?->status ?? ServiceHealthStatus::Unknown;
        $uptimePercent = $this->calculateUptimePercent($samples);

        return [
            'service' => $service,
            'label' => $service->label(),
            'currentStatus' => $currentStatus,
            'uptimePercent' => $uptimePercent,
            'uptimeLabel' => $uptimePercent === null
                ? 'No data'
                : number_format($uptimePercent, 1).'% uptime',
            'pieces' => $pieces,
        ];
    }

    /**
     * @param  Collection<int, ServiceHealthSample>  $samples
     * @return array{
     *     startsAt: CarbonInterface,
     *     endsAt: CarbonInterface,
     *     status: ServiceHealthStatus,
     *     detail: string,
     *     tooltip: string,
     *     ariaLabel: string,
     * }
     */
    private function buildPiece(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        Collection $samples,
    ): array {
        if ($samples->isEmpty()) {
            $detail = 'No health samples recorded for this period.';

            return [
                'startsAt' => $startsAt,
                'endsAt' => $endsAt,
                'status' => ServiceHealthStatus::Unknown,
                'detail' => $detail,
                'tooltip' => $this->buildTooltip($startsAt, $endsAt, ServiceHealthStatus::Unknown, $detail),
                'ariaLabel' => $this->buildAriaLabel($startsAt, $endsAt, ServiceHealthStatus::Unknown, $detail),
            ];
        }

        $isInProgress = $endsAt->isFuture();

        if ($isInProgress) {
            $latestSample = $samples->sortByDesc('checked_at')->first();
            $status = $latestSample?->status ?? ServiceHealthStatus::Unknown;
            $detail = $this->resolveLatestSampleDetail($latestSample, $status);
        } else {
            $status = ServiceHealthStatus::Operational;

            foreach ($samples as $sample) {
                if ($sample->status->isWorseThan($status)) {
                    $status = $sample->status;
                }
            }

            $detail = $this->resolvePieceDetail($samples, $status);
        }

        return [
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'status' => $status,
            'detail' => $detail,
            'tooltip' => $this->buildTooltip($startsAt, $endsAt, $status, $detail),
            'ariaLabel' => $this->buildAriaLabel($startsAt, $endsAt, $status, $detail),
        ];
    }

    /**
     * @param  Collection<int, ServiceHealthSample>  $samples
     */
    private function resolvePieceDetail(Collection $samples, ServiceHealthStatus $status): string
    {
        $matching = $samples
            ->filter(static fn (ServiceHealthSample $sample): bool => $sample->status === $status)
            ->sortByDesc('checked_at');

        foreach ($matching as $sample) {
            $message = data_get($sample->meta, 'message');

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return $status->label();
    }

    private function resolveLatestSampleDetail(?ServiceHealthSample $sample, ServiceHealthStatus $status): string
    {
        if ($sample === null) {
            return $status->label();
        }

        $message = data_get($sample->meta, 'message');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return $status->label();
    }

    /**
     * @param  Collection<int, ServiceHealthSample>  $samples
     */
    private function calculateUptimePercent(Collection $samples): ?float
    {
        if ($samples->isEmpty()) {
            return null;
        }

        $operationalCount = $samples
            ->filter(static fn (ServiceHealthSample $sample): bool => $sample->status === ServiceHealthStatus::Operational)
            ->count();

        return round(($operationalCount / $samples->count()) * 100, 1);
    }

    /**
     * @param  list<array{
     *     service: MonitoredService,
     *     label: string,
     *     currentStatus: ServiceHealthStatus,
     *     uptimePercent: float|null,
     *     uptimeLabel: string,
     *     pieces: list<array<string, mixed>>,
     * }>  $services
     * @return array{
     *     status: ServiceHealthStatus,
     *     title: string,
     *     message: string,
     * }
     */
    private function buildSummary(array $services): array
    {
        if ($services === []) {
            return [
                'status' => ServiceHealthStatus::Unknown,
                'title' => 'No services configured',
                'message' => 'No monitored services are available to report on.',
            ];
        }

        $currentStatuses = array_map(
            static fn (array $service): ServiceHealthStatus => $service['currentStatus'],
            $services,
        );

        $worstStatus = ServiceHealthStatus::worst(...$currentStatuses);

        if ($worstStatus === ServiceHealthStatus::Operational) {
            return [
                'status' => ServiceHealthStatus::Operational,
                'title' => "All services fully operational",
                'message' => 'No issues are currently affecting monitored services.',
           
            ];
        }

        if ($worstStatus === ServiceHealthStatus::Degraded) {
            $degradedCount = count(array_filter(
                $services,
                static fn (array $service): bool => $service['currentStatus'] === ServiceHealthStatus::Degraded,
            ));

            return [
                'status' => ServiceHealthStatus::Degraded,
                'title' => 'Partial degradation detected',
                'message' => $degradedCount === 1
                    ? '1 monitored service is degraded.'
                    : $degradedCount.' monitored services are degraded.',
            ];
        }

        if ($worstStatus === ServiceHealthStatus::Down) {
            $downCount = count(array_filter(
                $services,
                static fn (array $service): bool => $service['currentStatus'] === ServiceHealthStatus::Down,
            ));

            return [
                'status' => ServiceHealthStatus::Down,
                'title' => 'Major outage detected',
                'message' => $downCount === 1
                    ? '1 monitored service is down.'
                    : $downCount.' monitored services are down.',
            ];
        }

        return [
            'status' => ServiceHealthStatus::Unknown,
            'title' => 'Status data is still collecting',
            'message' => 'Run a health check or wait for the next scheduled probe.',
        ];
    }

    private function buildTooltip(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ServiceHealthStatus $status,
        string $detail,
    ): string {
        $lines = [
            $startsAt->format('D, j M Y'),
            sprintf('%s–%s', $startsAt->format('H:i'), $endsAt->format('H:i')),
            $status->label(),
            $detail,
        ];

        return collect($lines)
            ->map(static fn (string $line): string => e($line))
            ->implode('<br>');
    }

    private function buildAriaLabel(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ServiceHealthStatus $status,
        string $detail,
    ): string {
        return $this->formatPieceRange($startsAt, $endsAt).'. '.$status->label().'. '.$detail;
    }

    private function formatPieceRange(CarbonInterface $startsAt, CarbonInterface $endsAt): string
    {
        return sprintf(
            '%s · %s–%s',
            $startsAt->format('D, j M Y'),
            $startsAt->format('H:i'),
            $endsAt->format('H:i'),
        );
    }
}
