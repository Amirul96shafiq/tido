<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Budget extends Model
{
    use HasFactory, LogsActivity;

    protected $attributes = [
        'period' => 'monthly',
        'alert_threshold' => 80,
        'critical_threshold' => 100,
        'is_active' => true,
        'notify_filament' => true,
        'notify_whatsapp' => true,
    ];

    protected $fillable = [
        'title',
        'icon',
        'label_id',
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
        $start = $this->getStartDate($reference);
        $end = $this->getEndDate($reference);

        $query = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.date_time', [$start, $end])
            ->whereIn('invoices.status', ['parsed', 'reviewed']);

        if ($this->label_id) {
            $query->where('invoice_items.label_id', $this->label_id);
        }

        return (float) $query->sum('invoice_items.line_total');
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
