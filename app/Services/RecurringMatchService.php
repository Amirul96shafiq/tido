<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurringOccurrenceStatus;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use Illuminate\Support\Facades\DB;

class RecurringMatchService
{
    public const DUE_WINDOW_DAYS = 7;

    public function matchExpense(Expense $expense): ?RecurringOccurrence
    {
        if (! in_array($expense->status, ['parsed', 'reviewed'], true)) {
            return null;
        }

        if (RecurringOccurrence::query()->where('expense_id', $expense->id)->exists()) {
            return RecurringOccurrence::query()->where('expense_id', $expense->id)->first();
        }

        $best = $this->findMatchForExpense($expense);

        if ($best === null) {
            return null;
        }

        return $this->completeOccurrence($best, $expense);
    }

    /**
     * Rematch open occurrences against already-parsed/reviewed expenses (oldest first).
     *
     * @return array{
     *     scanned: int,
     *     matched: int,
     *     matches: list<array{
     *         expense_id: int,
     *         merchant_name: ?string,
     *         occurrence_id: int,
     *         recurring_id: int,
     *         recurring_title: string,
     *         due_on: string,
     *         actual_amount: ?string
     *     }>
     * }
     */
    public function matchParsedExpenses(bool $dryRun = false): array
    {
        $matches = [];
        $scanned = 0;

        Expense::query()
            ->whereIn('status', ['parsed', 'reviewed'])
            ->orderBy('date_time')
            ->orderBy('id')
            ->cursor()
            ->each(function (Expense $expense) use (&$matches, &$scanned, $dryRun): void {
                $scanned++;

                if (RecurringOccurrence::query()->where('expense_id', $expense->id)->exists()) {
                    return;
                }

                $best = $this->findMatchForExpense($expense);

                if ($best === null) {
                    return;
                }

                $recurring = $best->recurring;
                $title = $recurring instanceof Recurring ? $recurring->title : 'Recurring';

                $matches[] = [
                    'expense_id' => (int) $expense->id,
                    'merchant_name' => $expense->merchant_name,
                    'occurrence_id' => (int) $best->id,
                    'recurring_id' => (int) $best->recurring_id,
                    'recurring_title' => $title,
                    'due_on' => $best->due_on->toDateString(),
                    'actual_amount' => $expense->total_amount !== null
                        ? (string) $expense->total_amount
                        : null,
                ];

                if (! $dryRun) {
                    $this->completeOccurrence($best, $expense);
                }
            });

        return [
            'scanned' => $scanned,
            'matched' => count($matches),
            'matches' => $matches,
        ];
    }

    public function findMatchForExpense(Expense $expense): ?RecurringOccurrence
    {
        if (! in_array($expense->status, ['parsed', 'reviewed'], true)) {
            return null;
        }

        $expenseDate = $expense->date_time?->copy()->startOfDay() ?? now()->startOfDay();
        $windowStart = $expenseDate->copy()->subDays(self::DUE_WINDOW_DAYS);
        $windowEnd = $expenseDate->copy()->addDays(self::DUE_WINDOW_DAYS);

        $candidates = RecurringOccurrence::query()
            ->open()
            ->with('recurring')
            ->whereBetween('due_on', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->whereHas('recurring', function ($query) use ($expense): void {
                $query->active()->where(function ($inner) use ($expense): void {
                    $inner->where('is_shared', true);

                    if ($expense->family_member_id === null) {
                        $inner->orWhere(function ($primary): void {
                            $primary->where('is_shared', false)->whereNull('family_member_id');
                        });
                    } else {
                        $inner->orWhere(function ($owned) use ($expense): void {
                            $owned
                                ->where('is_shared', false)
                                ->where('family_member_id', $expense->family_member_id);
                        });
                    }
                });
            })
            ->orderBy('due_on')
            ->get();

        $labelIds = $expense->expenseItems()
            ->pluck('label_id')
            ->filter()
            ->unique()
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $best = null;
        $bestScore = -1;

        foreach ($candidates as $occurrence) {
            $recurring = $occurrence->recurring;

            if (! $recurring instanceof Recurring || ! $recurring->appliesToExpense($expense)) {
                continue;
            }

            if (! $recurring->merchantMatches($expense->merchant_name)) {
                continue;
            }

            $score = 10;

            if ($recurring->label_id !== null && in_array((int) $recurring->label_id, $labelIds, true)) {
                $score += 5;
            }

            $daysDiff = abs($occurrence->due_on->diffInDays($expenseDate));
            $score += max(0, self::DUE_WINDOW_DAYS - $daysDiff);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $occurrence;
            }
        }

        return $best;
    }

    public function completeOccurrence(RecurringOccurrence $occurrence, Expense $expense): RecurringOccurrence
    {
        return DB::transaction(function () use ($occurrence, $expense): RecurringOccurrence {
            /** @var RecurringOccurrence $locked */
            $locked = RecurringOccurrence::query()->lockForUpdate()->findOrFail($occurrence->id);

            if (! $locked->isOpen()) {
                return $locked->fresh(['recurring']) ?? $locked;
            }

            $locked->status = RecurringOccurrenceStatus::Completed;
            $locked->expense_id = $expense->id;
            $locked->actual_amount = $expense->total_amount;
            $locked->save();

            $recurring = $locked->recurring;

            if ($recurring instanceof Recurring) {
                $recurring->decrementInstalmentRemaining();
            }

            return $locked->fresh(['recurring', 'expense']) ?? $locked;
        });
    }

    public function skipOccurrence(RecurringOccurrence $occurrence): RecurringOccurrence
    {
        if (! $occurrence->isOpen()) {
            return $occurrence;
        }

        $occurrence->status = RecurringOccurrenceStatus::Skipped;
        $occurrence->save();

        $recurring = $occurrence->recurring;

        if ($recurring instanceof Recurring) {
            $recurring->decrementInstalmentRemaining();
        }

        return $occurrence->fresh() ?? $occurrence;
    }
}
