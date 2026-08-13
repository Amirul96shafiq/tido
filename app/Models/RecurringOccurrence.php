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
}
