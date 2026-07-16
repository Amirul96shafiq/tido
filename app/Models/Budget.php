<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Budget extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'label_id',
        'amount',
        'period',
        'quarter',
        'year',
        'alert_threshold',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quarter' => 'integer',
        'year' => 'integer',
        'alert_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function getGlobalSearchTitleAttribute(): string
    {
        $label = $this->label?->name ?? 'Overall';

        return "{$label} · ".ucfirst((string) $this->period)." {$this->year}";
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
