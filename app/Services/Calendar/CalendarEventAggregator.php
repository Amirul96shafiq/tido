<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\User;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class CalendarEventAggregator
{
    /**
     * @var list<CalendarEventProvider>
     */
    private array $providers = [];

    public function register(CalendarEventProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return list<array{key: string, label: string, module: string}>
     */
    public function availableFilters(): array
    {
        return array_map(
            static fn (CalendarEventProvider $provider): array => [
                'key' => $provider->filterKey(),
                'label' => $provider->filterLabel(),
                'module' => $provider->module()->value,
            ],
            $this->providers,
        );
    }

    /**
     * @param  list<string>  $activeFilterKeys
     * @return Collection<string, Collection<int, CalendarEvent>>
     */
    public function eventsGroupedByDate(
        CarbonInterface $start,
        CarbonInterface $end,
        User $viewer,
        array $activeFilterKeys,
    ): Collection {
        $events = $this->eventsForRange($start, $end, $viewer, $activeFilterKeys);

        return $events->groupBy(
            static fn (CalendarEvent $event): string => $event->date->toDateString(),
        );
    }

    /**
     * @param  list<string>  $activeFilterKeys
     * @return Collection<int, CalendarEvent>
     */
    public function eventsForRange(
        CarbonInterface $start,
        CarbonInterface $end,
        User $viewer,
        array $activeFilterKeys,
    ): Collection {
        $events = collect();

        foreach ($this->providers as $provider) {
            if (! in_array($provider->filterKey(), $activeFilterKeys, true)) {
                continue;
            }

            $events = $events->merge(
                $provider->eventsForRange($start, $end, $viewer),
            );
        }

        return $events->sortBy([
            ['date', 'asc'],
            ['title', 'asc'],
        ])->values();
    }
}
