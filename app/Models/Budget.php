<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TracksResourceEdits;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Budget extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, TracksResourceEdits;

    protected $attributes = [
        'period' => 'monthly',
        'alert_threshold' => 80,
        'critical_threshold' => 100,
        'is_active' => true,
        'is_shared' => false,
        'notify_filament' => true,
        'notify_whatsapp' => true,
    ];

    protected $fillable = [
        'title',
        'icon',
        'label_id',
        'family_member_id',
        'is_shared',
        'amount',
        'period',
        'quarter',
        'year',
        'alert_threshold',
        'critical_threshold',
        'notify_filament',
        'notify_whatsapp',
        'is_active',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quarter' => 'integer',
        'year' => 'integer',
        'alert_threshold' => 'integer',
        'critical_threshold' => 'integer',
        'notify_filament' => 'boolean',
        'notify_whatsapp' => 'boolean',
        'is_active' => 'boolean',
        'is_shared' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Budget $budget): void {
            if ($budget->sort_order !== null) {
                return;
            }

            $budget->sort_order = (int) static::query()->max('sort_order') + 1;
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
     * @return Attribute<string, never>
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::get(function (): string {
            if (filled($this->title)) {
                return (string) $this->title;
            }

            return $this->label?->name ?? 'Overall Budget';
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayIcon(): Attribute
    {
        return Attribute::get(function (): string {
            if (filled($this->icon)) {
                return (string) $this->icon;
            }

            if (filled($this->label?->icon)) {
                return (string) $this->label->icon;
            }

            return 'heroicon-o-banknotes';
        });
    }

    public function getGlobalSearchTitleAttribute(): string
    {
        return "{$this->display_title} · ".ucfirst((string) $this->period)." {$this->year}";
    }

    public function spentInPeriod(?Carbon $reference = null): float
    {
        return self::spentForPreview($this, $reference);
    }

    public static function spentForPreview(self $preview, ?Carbon $reference = null): float
    {
        $totals = self::spentTotalsFor(collect([$preview]), $reference);

        return (float) ($totals[(int) $preview->getKey()] ?? 0.0);
    }

    /**
     * Batch spent totals for many budgets using one aggregate query per date window.
     *
     * @param  Collection<int, self>  $budgets
     * @return array<int, float>
     */
    public static function spentTotalsFor(Collection $budgets, ?Carbon $reference = null): array
    {
        $reference ??= now();
        $totals = [];

        foreach ($budgets as $budget) {
            $totals[(int) $budget->getKey()] = 0.0;
        }

        if ($budgets->isEmpty()) {
            return $totals;
        }

        $windows = $budgets->groupBy(
            fn (self $budget): string => $budget->getStartDate($reference)->format('c')
                .'|'.$budget->getEndDate($reference)->format('c'),
        );

        foreach ($windows as $windowBudgets) {
            /** @var self $first */
            $first = $windowBudgets->first();
            $start = $first->getStartDate($reference);
            $end = $first->getEndDate($reference);

            $rows = ExpenseItem::query()
                ->join('expenses', 'expense_items.expense_id', '=', 'expenses.id')
                ->whereNull('expenses.deleted_at')
                ->whereBetween('expenses.date_time', [$start, $end])
                ->whereIn('expenses.status', ['parsed', 'reviewed'])
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('expenses.document_classification')
                        ->orWhere(
                            'expenses.document_classification',
                            Expense::DOCUMENT_CLASSIFICATION_RECEIPT,
                        );
                })
                ->where('expenses.currency', Expense::CURRENCY_MYR)
                ->whereIn('expenses.currency_conversion_status', Expense::CANONICAL_CONVERSION_STATUSES)
                ->selectRaw('expense_items.label_id as label_id, expenses.family_member_id as family_member_id, SUM(expense_items.line_total) as total')
                ->groupBy('expense_items.label_id', 'expenses.family_member_id')
                ->get();

            foreach ($windowBudgets as $budget) {
                $totals[(int) $budget->getKey()] = (float) $rows
                    ->filter(fn ($row): bool => self::rowAppliesToBudget($budget, $row))
                    ->sum(fn ($row): float => (float) $row->total);
            }
        }

        return $totals;
    }

    private static function rowAppliesToBudget(self $budget, object $row): bool
    {
        if ($budget->label_id !== null && (int) $row->label_id !== (int) $budget->label_id) {
            return false;
        }

        if ($budget->is_shared) {
            return true;
        }

        if ($budget->family_member_id === null) {
            return $row->family_member_id === null;
        }

        return $row->family_member_id !== null
            && (int) $row->family_member_id === (int) $budget->family_member_id;
    }

    public function getStartDate(?Carbon $reference = null): Carbon
    {
        $reference ??= now();
        $currentYear = (int) ($this->year ?: $reference->year);

        return match ($this->period) {
            'daily' => $reference->copy()->startOfDay(),
            'weekly' => $reference->copy()->startOfWeek(),
            'monthly' => $reference->copy()->startOfMonth(),
            'quarterly' => $this->getQuarterStartDate($currentYear),
            'yearly' => Carbon::create($currentYear, 1, 1)->startOfDay(),
            default => $reference->copy()->startOfMonth(),
        };
    }

    public function getEndDate(?Carbon $reference = null): Carbon
    {
        $reference ??= now();
        $currentYear = (int) ($this->year ?: $reference->year);

        return match ($this->period) {
            'daily' => $reference->copy()->endOfDay(),
            'weekly' => $reference->copy()->endOfWeek(),
            'monthly' => $reference->copy()->endOfMonth(),
            'quarterly' => $this->getQuarterEndDate($currentYear),
            'yearly' => Carbon::create($currentYear, 12, 31)->endOfDay(),
            default => $reference->copy()->endOfMonth(),
        };
    }

    private function getQuarterStartDate(int $year): Carbon
    {
        $quarter = (int) ($this->quarter ?: 1);
        $month = (($quarter - 1) * 3) + 1;

        return Carbon::create($year, $month, 1)->startOfDay();
    }

    private function getQuarterEndDate(int $year): Carbon
    {
        $quarter = (int) ($this->quarter ?: 1);
        $month = (($quarter - 1) * 3) + 3;

        return Carbon::create($year, $month, 1)->endOfMonth();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
