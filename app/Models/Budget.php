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
        'labeling_id',
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

    public function labeling(): BelongsTo
    {
        return $this->belongsTo(Labeling::class);
    }

    public function getStartDate(): Carbon
    {
        $currentYear = (int) ($this->year ?: now()->year);

        return match ($this->period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'quarterly' => $this->getQuarterStartDate($currentYear),
            'yearly' => Carbon::create($currentYear, 1, 1)->startOfDay(),
            default => now()->startOfMonth(),
        };
    }

    public function getEndDate(): Carbon
    {
        $currentYear = (int) ($this->year ?: now()->year);

        return match ($this->period) {
            'daily' => now()->endOfDay(),
            'weekly' => now()->endOfWeek(),
            'monthly' => now()->endOfMonth(),
            'quarterly' => $this->getQuarterEndDate($currentYear),
            'yearly' => Carbon::create($currentYear, 12, 31)->endOfDay(),
            default => now()->endOfMonth(),
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
