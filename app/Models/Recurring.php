<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Enums\RecurringType;
use App\Models\Concerns\TracksResourceEdits;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Recurring extends Model
{
    use HasFactory, LogsActivity, TracksResourceEdits;

    protected $attributes = [
        'frequency' => 'repeating',
        'is_shared' => false,
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'title',
        'notes',
        'type',
        'label_id',
        'family_member_id',
        'is_shared',
        'expected_amount',
        'goal_target_amount',
        'frequency',
        'interval_months',
        'anchor_day',
        'starts_on',
        'ends_on',
        'next_due_on',
        'instalment_total',
        'instalment_remaining',
        'merchant_aliases',
        'notify_filament',
        'notify_whatsapp',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'type' => RecurringType::class,
        'frequency' => RecurringFrequency::class,
        'expected_amount' => 'decimal:2',
        'goal_target_amount' => 'decimal:2',
        'interval_months' => 'integer',
        'anchor_day' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'next_due_on' => 'date',
        'instalment_total' => 'integer',
        'instalment_remaining' => 'integer',
        'merchant_aliases' => 'array',
        'notify_filament' => 'boolean',
        'notify_whatsapp' => 'boolean',
        'is_active' => 'boolean',
        'is_shared' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Recurring $recurring): void {
            if ($recurring->sort_order === null || (int) $recurring->sort_order === 0) {
                $recurring->sort_order = (int) static::query()->max('sort_order') + 1;
            }

            $recurring->normalizeCommitmentFields();

            if ($recurring->next_due_on === null) {
                $recurring->next_due_on = $recurring->resolveInitialDueOn();
            }
        });

        static::updating(function (Recurring $recurring): void {
            $recurring->normalizeCommitmentFields();
        });
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringOccurrence::class, 'recurring_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->isPrimary()) {
            return $query;
        }

        if (! $user->isFamilyMember() || $user->family_member_id === null) {
            return $query->whereRaw('0 = 1');
        }

        $familyMemberId = (int) $user->family_member_id;

        return $query->where(function (Builder $inner) use ($familyMemberId): void {
            $inner
                ->where('is_shared', true)
                ->orWhere('family_member_id', $familyMemberId);
        });
    }

    public function appliesToExpense(Expense $expense): bool
    {
        if ($this->is_shared) {
            return true;
        }

        if ($this->family_member_id === null) {
            return $expense->family_member_id === null;
        }

        return $expense->family_member_id !== null
            && (int) $expense->family_member_id === (int) $this->family_member_id;
    }

    /**
     * @return list<string>
     */
    public function normalizedMerchantAliases(): array
    {
        $aliases = is_array($this->merchant_aliases) ? $this->merchant_aliases : [];
        $aliases[] = $this->title;

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $alias): string => mb_strtolower(trim((string) $alias)),
            $aliases,
        ), static fn (string $alias): bool => $alias !== '')));
    }

    public function merchantMatches(?string $merchantName): bool
    {
        $merchant = mb_strtolower(trim((string) $merchantName));

        if ($merchant === '') {
            return false;
        }

        foreach ($this->normalizedMerchantAliases() as $alias) {
            if ($alias === $merchant || str_contains($merchant, $alias) || str_contains($alias, $merchant)) {
                return true;
            }
        }

        return false;
    }

    public function goalProgressAmount(): float
    {
        return (float) $this->occurrences()
            ->where('status', RecurringOccurrenceStatus::Completed)
            ->sum('actual_amount');
    }

    public function goalProgressPercent(): ?float
    {
        $target = $this->goal_target_amount;

        if ($target === null || (float) $target <= 0) {
            return null;
        }

        return min(100, round(($this->goalProgressAmount() / (float) $target) * 100, 2));
    }

    public function canGenerateMoreOccurrences(?CarbonInterface $reference = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->instalment_remaining !== null && $this->instalment_remaining <= 0) {
            return false;
        }

        $reference ??= now();

        if ($this->ends_on !== null && $reference->toDateString() > $this->ends_on->toDateString()) {
            return false;
        }

        if ($this->frequency === RecurringFrequency::Once) {
            return ! $this->occurrences()->exists();
        }

        return $this->next_due_on !== null;
    }

    public function periodBoundsForDueOn(CarbonInterface $dueOn): array
    {
        $due = Carbon::parse($dueOn)->startOfDay();

        if ($this->frequency === RecurringFrequency::Once) {
            return [$due->copy(), $due->copy()];
        }

        $months = max(1, (int) ($this->interval_months ?? 1));
        $periodStart = $due->copy();
        $periodEnd = $due->copy()->addMonthsNoOverflow($months)->subDay();

        return [$periodStart, $periodEnd];
    }

    public function advanceNextDueOn(): void
    {
        if ($this->frequency === RecurringFrequency::Once) {
            $this->next_due_on = null;
            $this->save();

            return;
        }

        if ($this->next_due_on === null) {
            return;
        }

        $months = max(1, (int) ($this->interval_months ?? 1));
        $next = $this->next_due_on->copy()->addMonthsNoOverflow($months);
        $next = $this->applyAnchorDay($next);

        if ($this->ends_on !== null && $next->toDateString() > $this->ends_on->toDateString()) {
            $this->next_due_on = null;
        } else {
            $this->next_due_on = $next;
        }

        $this->save();
    }

    public function decrementInstalmentRemaining(): void
    {
        if ($this->instalment_remaining === null) {
            return;
        }

        $this->instalment_remaining = max(0, $this->instalment_remaining - 1);
        $this->save();
    }

    public function incrementInstalmentRemaining(): void
    {
        if ($this->instalment_remaining === null) {
            return;
        }

        $cap = $this->instalment_total ?? $this->instalment_remaining + 1;

        $this->instalment_remaining = min($cap, $this->instalment_remaining + 1);
        $this->save();
    }

    public function resolveInitialDueOn(?CarbonInterface $reference = null): ?Carbon
    {
        $reference = Carbon::parse($reference ?? $this->starts_on ?? now())->startOfDay();

        if ($this->frequency === RecurringFrequency::Once) {
            return $this->applyAnchorDay($reference);
        }

        return $this->applyAnchorDay($reference);
    }

    public function normalizeCommitmentFields(): void
    {
        if ($this->frequency === RecurringFrequency::Once) {
            $this->interval_months = null;

            return;
        }

        if ($this->interval_months === null || $this->interval_months < 1) {
            $this->interval_months = 1;
        }

        if ($this->interval_months > 24) {
            $this->interval_months = 24;
        }

        if (
            $this->goal_target_amount !== null
            && (float) $this->goal_target_amount > 0
            && $this->expected_amount !== null
            && (float) $this->expected_amount > 0
            && $this->instalment_total === null
        ) {
            $this->instalment_total = (int) ceil((float) $this->goal_target_amount / (float) $this->expected_amount);
        }

        if ($this->instalment_total !== null && $this->instalment_remaining === null) {
            $this->instalment_remaining = $this->instalment_total;
        }
    }

    private function applyAnchorDay(Carbon $date): Carbon
    {
        $day = $this->anchor_day;

        if ($day === null || $day < 1) {
            return $date->startOfDay();
        }

        $day = min(28, max(1, $day));
        $clamped = min($day, $date->daysInMonth);

        return $date->copy()->day($clamped)->startOfDay();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
