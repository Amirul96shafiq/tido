<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LabelingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Labeling extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'is_system' => false,
    ];

    protected $fillable = [
        'type',
        'name',
        'slug',
        'icon',
        'color',
        'is_system',
    ];

    protected $casts = [
        'type' => LabelingType::class,
        'is_system' => 'boolean',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, LabelingType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
