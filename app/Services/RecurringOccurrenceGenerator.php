<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use Carbon\Carbon;

class RecurringOccurrenceGenerator
{
    /**
     * Generate upcoming occurrences and refresh due/overdue statuses.
     *
     * @return array{generated: int, status_updates: int}
     */
    public function run(?Carbon $reference = null): array
    {
        $reference ??= now()->startOfDay();
        $generated = 0;

        Recurring::query()
            ->active()
            ->orderBy('id')
            ->each(function (Recurring $recurring) use ($reference, &$generated): void {
                $generated += $this->generateFor($recurring, $reference);
            });

        $statusUpdates = $this->refreshStatuses($reference);

        return [
            'generated' => $generated,
            'status_updates' => $statusUpdates,
        ];
    }

    public function generateFor(Recurring $recurring, ?Carbon $reference = null): int
    {
        $reference ??= now()->startOfDay();
        $created = 0;
        $horizon = $reference->copy()->addDays(45);

        $this->pruneStaleOpenOccurrences($recurring);

        while ($recurring->canGenerateMoreOccurrences($reference) && $recurring->next_due_on !== null) {
            $dueOn = $recurring->next_due_on->copy()->startOfDay();

            if ($dueOn->greaterThan($horizon)) {
                break;
            }

            if ($recurring->ends_on !== null && $dueOn->toDateString() > $recurring->ends_on->toDateString()) {
                $recurring->next_due_on = null;
                $recurring->save();
                break;
            }

            [$periodStart, $periodEnd] = $recurring->periodBoundsForDueOn($dueOn);

            $exists = RecurringOccurrence::query()
                ->where('recurring_id', $recurring->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->exists();

            if (! $exists) {
                $status = $this->resolveStatusForDueOn($dueOn, $reference);

                RecurringOccurrence::query()->create([
                    'recurring_id' => $recurring->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'due_on' => $dueOn->toDateString(),
                    'status' => $status,
                    'expected_amount' => $recurring->expected_amount,
                ]);

                $created++;
            }

            if ($recurring->frequency === RecurringFrequency::Once) {
                $recurring->next_due_on = null;
                $recurring->save();
                break;
            }

            $months = max(1, (int) ($recurring->interval_months ?? 1));
            $next = $dueOn->copy()->addMonthsNoOverflow($months);
            $anchor = $recurring->anchor_day;

            if ($anchor !== null && $anchor >= 1) {
                $day = min(28, max(1, $anchor));
                $next->day(min($day, $next->daysInMonth));
            }

            if ($recurring->ends_on !== null && $next->toDateString() > $recurring->ends_on->toDateString()) {
                $recurring->next_due_on = null;
            } else {
                $recurring->next_due_on = $next;
            }

            $recurring->save();
            $recurring->refresh();
        }

        return $created;
    }

    public function refreshStatuses(?Carbon $reference = null): int
    {
        $reference ??= now()->startOfDay();
        $updated = 0;

        RecurringOccurrence::query()
            ->open()
            ->orderBy('id')
            ->each(function (RecurringOccurrence $occurrence) use ($reference, &$updated): void {
                $nextStatus = $this->resolveStatusForDueOn($occurrence->due_on->copy()->startOfDay(), $reference);

                if ($occurrence->status === $nextStatus) {
                    return;
                }

                $occurrence->status = $nextStatus;
                $occurrence->save();
                $updated++;
            });

        return $updated;
    }

    public function statusForDueOn(Carbon $dueOn, ?Carbon $reference = null): RecurringOccurrenceStatus
    {
        $reference ??= now()->startOfDay();

        return $this->resolveStatusForDueOn($dueOn, $reference);
    }

    /**
     * Drop open occurrences whose period no longer matches the recurring cadence.
     * Completed and skipped history is preserved.
     */
    public function pruneStaleOpenOccurrences(Recurring $recurring): int
    {
        if ($recurring->frequency === RecurringFrequency::Once) {
            return 0;
        }

        $deleted = 0;

        $open = RecurringOccurrence::query()
            ->where('recurring_id', $recurring->id)
            ->open()
            ->get();

        foreach ($open as $occurrence) {
            [$periodStart, $periodEnd] = $recurring->periodBoundsForDueOn($occurrence->due_on);

            if (
                $occurrence->period_start?->toDateString() !== $periodStart->toDateString()
                || $occurrence->period_end?->toDateString() !== $periodEnd->toDateString()
            ) {
                $occurrence->delete();
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * When next_due_on is moved forward, discard earlier open rows so the dues
     * widget does not keep showing superseded dates.
     */
    public function discardOpenOccurrencesBeforeNextDue(Recurring $recurring): int
    {
        if ($recurring->next_due_on === null) {
            return 0;
        }

        return RecurringOccurrence::query()
            ->where('recurring_id', $recurring->id)
            ->open()
            ->whereDate('due_on', '<', $recurring->next_due_on->toDateString())
            ->delete();
    }

    private function resolveStatusForDueOn(Carbon $dueOn, Carbon $reference): RecurringOccurrenceStatus
    {
        $due = $dueOn->toDateString();
        $today = $reference->toDateString();

        if ($due < $today) {
            return RecurringOccurrenceStatus::Overdue;
        }

        if ($due === $today) {
            return RecurringOccurrenceStatus::Due;
        }

        if ($dueOn->lessThanOrEqualTo($reference->copy()->addDays(7))) {
            return RecurringOccurrenceStatus::Due;
        }

        return RecurringOccurrenceStatus::Upcoming;
    }
}
