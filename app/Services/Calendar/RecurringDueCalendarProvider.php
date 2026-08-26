<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Helpers\MoneyDisplay;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use App\Support\Calendar\CalendarModule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class RecurringDueCalendarProvider implements CalendarEventProvider
{
    private const MAX_PROJECTION_STEPS = 120;

    public function module(): CalendarModule
    {
        return CalendarModule::Finances;
    }

    public function filterKey(): string
    {
        return 'recurring_dues';
    }

    public function filterLabel(): string
    {
        return 'Recurring Dues';
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    public function eventsForRange(CarbonInterface $start, CarbonInterface $end, User $viewer): Collection
    {
        $rangeStart = Carbon::parse($start)->startOfDay();
        $rangeEnd = Carbon::parse($end)->endOfDay();

        $occurrenceEvents = $this->occurrenceEvents($rangeStart, $rangeEnd, $viewer);
        $projectedEvents = $this->projectedEvents($rangeStart, $rangeEnd, $viewer);

        return $occurrenceEvents
            ->concat($projectedEvents)
            ->sortBy([
                ['date', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    private function occurrenceEvents(Carbon $rangeStart, Carbon $rangeEnd, User $viewer): Collection
    {
        return RecurringOccurrence::query()
            ->visibleTo($viewer)
            ->whereHas('recurring', fn ($query) => $query->active())
            ->whereDate('due_on', '>=', $rangeStart->toDateString())
            ->whereDate('due_on', '<=', $rangeEnd->toDateString())
            ->with(['recurring.label'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(fn (RecurringOccurrence $occurrence): CalendarEvent => $this->eventFromOccurrence($occurrence));
    }

    /**
     * @param  Collection<string, int>  $coveredDates
     * @return Collection<int, CalendarEvent>
     */
    private function projectedEvents(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        User $viewer,
    ): Collection {
        $events = collect();

        Recurring::query()
            ->active()
            ->visibleTo($viewer)
            ->orderBy('id')
            ->each(function (Recurring $recurring) use ($rangeStart, $rangeEnd, &$events): void {
                $existingDates = RecurringOccurrence::query()
                    ->where('recurring_id', $recurring->id)
                    ->whereDate('due_on', '>=', $rangeStart->toDateString())
                    ->whereDate('due_on', '<=', $rangeEnd->toDateString())
                    ->pluck('due_on')
                    ->map(static fn (mixed $date): string => Carbon::parse((string) $date)->toDateString());

                foreach ($this->projectDueDates($recurring, $rangeStart, $rangeEnd) as $dueOn) {
                    $dateKey = $dueOn->toDateString();

                    if ($existingDates->contains($dateKey)) {
                        continue;
                    }

                    $events->push($this->eventFromRecurring($recurring, $dueOn, projected: true));
                }
            });

        return $events;
    }

    /**
     * @return list<Carbon>
     */
    private function projectDueDates(Recurring $recurring, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if (! $recurring->canGenerateMoreOccurrences($rangeStart)) {
            return [];
        }

        $cursor = $recurring->nextOpenDueOn() ?? $recurring->resolveInitialDueOn($rangeStart);

        if ($cursor === null) {
            return [];
        }

        $dates = [];
        $steps = 0;

        while ($cursor !== null && $steps < self::MAX_PROJECTION_STEPS) {
            if ($cursor->greaterThan($rangeEnd)) {
                break;
            }

            if ($cursor->greaterThanOrEqualTo($rangeStart)) {
                $dates[] = $cursor->copy()->startOfDay();
            }

            if ($recurring->frequency === RecurringFrequency::Once) {
                break;
            }

            $months = max(1, (int) ($recurring->interval_months ?? 1));
            $next = $cursor->copy()->addMonthsNoOverflow($months);
            $anchor = $recurring->anchor_day;

            if ($anchor !== null && $anchor >= 1) {
                $day = min(28, max(1, (int) $anchor));
                $next->day(min($day, $next->daysInMonth));
            }

            if ($recurring->ends_on !== null && $next->toDateString() > $recurring->ends_on->toDateString()) {
                break;
            }

            $cursor = $next->startOfDay();
            $steps++;
        }

        return $dates;
    }

    private function eventFromOccurrence(RecurringOccurrence $occurrence): CalendarEvent
    {
        $recurring = $occurrence->recurring;
        $status = $occurrence->status ?? RecurringOccurrenceStatus::Upcoming;
        $amount = $occurrence->resolvedExpectedAmount();

        return new CalendarEvent(
            module: CalendarModule::Finances,
            type: 'recurring_due',
            date: $occurrence->due_on->copy()->startOfDay(),
            title: (string) ($recurring?->title ?? 'Recurring'),
            subtitle: $amount !== null ? MoneyDisplay::withPrefix($amount) : null,
            status: $status->label(),
            colorKey: $this->colorKeyForStatus($status),
            url: $recurring !== null ? $this->recurringViewUrl($recurring) : null,
            meta: [
                'occurrence_id' => $occurrence->id,
                'recurring_id' => $occurrence->recurring_id,
                'projected' => false,
                'completed' => $status === RecurringOccurrenceStatus::Completed,
            ],
        );
    }

    private function eventFromRecurring(Recurring $recurring, Carbon $dueOn, bool $projected): CalendarEvent
    {
        $reference = now()->startOfDay();
        $status = $dueOn->lt($reference)
            ? RecurringOccurrenceStatus::Overdue
            : ($dueOn->eq($reference)
                ? RecurringOccurrenceStatus::Due
                : RecurringOccurrenceStatus::Upcoming);

        return new CalendarEvent(
            module: CalendarModule::Finances,
            type: 'recurring_due',
            date: $dueOn->copy()->startOfDay(),
            title: $recurring->title,
            subtitle: $recurring->expected_amount !== null
                ? MoneyDisplay::withPrefix($recurring->expected_amount)
                : null,
            status: $projected ? 'Scheduled' : $status->label(),
            colorKey: $projected ? 'scheduled' : $this->colorKeyForStatus($status),
            url: $this->recurringViewUrl($recurring),
            meta: [
                'recurring_id' => $recurring->id,
                'projected' => $projected,
            ],
        );
    }

    private function recurringViewUrl(Recurring $recurring): string
    {
        return RecurringResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $recurring->getRouteKey(),
        ]);
    }

    private function colorKeyForStatus(RecurringOccurrenceStatus $status): string
    {
        return match ($status) {
            RecurringOccurrenceStatus::Overdue => 'danger',
            RecurringOccurrenceStatus::Due => 'warning',
            RecurringOccurrenceStatus::Upcoming => 'primary',
            RecurringOccurrenceStatus::Completed => 'success',
            RecurringOccurrenceStatus::Skipped => 'muted',
        };
    }
}
