<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurringOccurrenceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOccurrence extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'upcoming',
    ];

    protected $fillable = [
        'recurring_id',
        'period_start',
        'period_end',
        'due_on',
        'status',
        'expected_amount',
        'actual_amount',
        'expense_id',
        'reminded_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_on' => 'date',
        'status' => RecurringOccurrenceStatus::class,
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'reminded_at' => 'datetime',
    ];

    public function recurring(): BelongsTo
    {
        return $this->belongsTo(Recurring::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RecurringOccurrenceStatus::Upcoming,
            RecurringOccurrenceStatus::Due,
            RecurringOccurrenceStatus::Overdue,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->whereHas('recurring', function (Builder $recurring) use ($user): void {
            $recurring->visibleTo($user);
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForDashboardMonth(Builder $query): Builder
    {
        $monthStart = now()->copy()->startOfMonth();
        $monthEnd = now()->copy()->endOfMonth();

        return $query
            ->whereHas('recurring', fn ($recurring) => $recurring->active())
            ->where(function (Builder $inner) use ($monthStart, $monthEnd): void {
                $inner
                    ->whereIn('status', [
                        RecurringOccurrenceStatus::Due,
                        RecurringOccurrenceStatus::Overdue,
                    ])
                    ->orWhere(function (Builder $upcoming) use ($monthStart, $monthEnd): void {
                        $upcoming
                            ->where('status', RecurringOccurrenceStatus::Upcoming)
                            ->whereDate('due_on', '>=', $monthStart->toDateString())
                            ->whereDate('due_on', '<=', $monthEnd->toDateString());
                    })
                    ->orWhere(function (Builder $orphanUpcoming) use ($monthStart, $monthEnd): void {
                        $orphanUpcoming
                            ->where('status', RecurringOccurrenceStatus::Upcoming)
                            ->whereDate('due_on', '>', $monthEnd->toDateString())
                            ->where(
                                'due_on',
                                '=',
                                static function ($nextOpen) use ($monthEnd): void {
                                    $nextOpen
                                        ->from('recurring_occurrences as next_open')
                                        ->selectRaw('min(next_open.due_on)')
                                        ->whereColumn('next_open.recurring_id', 'recurring_occurrences.recurring_id')
                                        ->where('next_open.status', RecurringOccurrenceStatus::Upcoming->value)
                                        ->whereDate('next_open.due_on', '>', $monthEnd->toDateString());
                                },
                            )
                            ->whereNotExists(function ($exists) use ($monthStart, $monthEnd): void {
                                $exists
                                    ->selectRaw('1')
                                    ->from('recurring_occurrences as month_siblings')
                                    ->whereColumn('month_siblings.recurring_id', 'recurring_occurrences.recurring_id')
                                    ->where(function ($visible) use ($monthStart, $monthEnd): void {
                                        $visible
                                            ->whereIn('month_siblings.status', [
                                                RecurringOccurrenceStatus::Due->value,
                                                RecurringOccurrenceStatus::Overdue->value,
                                            ])
                                            ->orWhere(function ($upcoming) use ($monthStart, $monthEnd): void {
                                                $upcoming
                                                    ->where('month_siblings.status', RecurringOccurrenceStatus::Upcoming->value)
                                                    ->whereDate('month_siblings.due_on', '>=', $monthStart->toDateString())
                                                    ->whereDate('month_siblings.due_on', '<=', $monthEnd->toDateString());
                                            })
                                            ->orWhere(function ($completed) use ($monthStart): void {
                                                $completed
                                                    ->where('month_siblings.status', RecurringOccurrenceStatus::Completed->value)
                                                    ->where('month_siblings.updated_at', '>=', $monthStart);
                                            })
                                            ->orWhere(function ($skipped) use ($monthStart): void {
                                                $skipped
                                                    ->where('month_siblings.status', RecurringOccurrenceStatus::Skipped->value)
                                                    ->where('month_siblings.updated_at', '>=', $monthStart);
                                            });
                                    });
                            });
                    })
                    ->orWhere(function (Builder $completed) use ($monthStart): void {
                        $completed
                            ->where('status', RecurringOccurrenceStatus::Completed)
                            ->where('updated_at', '>=', $monthStart);
                    })
                    ->orWhere(function (Builder $skipped) use ($monthStart): void {
                        $skipped
                            ->where('status', RecurringOccurrenceStatus::Skipped)
                            ->where('updated_at', '>=', $monthStart);
                    });
            });
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function resolvedExpectedAmount(): mixed
    {
        $this->loadMissing('recurring');

        $snapshot = $this->expected_amount;
        $template = $this->recurring?->expected_amount;

        if ($template !== null && ($snapshot === null || (float) $snapshot === 0.0)) {
            return $template;
        }

        return $snapshot;
    }
}
