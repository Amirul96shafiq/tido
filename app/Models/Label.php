<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LabelType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Label extends Model
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
        'description',
        'is_system',
    ];

    protected $casts = [
        'type' => LabelType::class,
        'is_system' => 'boolean',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, LabelType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @return Collection<int, self>
     */
    public static function financeLabels(): Collection
    {
        return static::query()
            ->ofType(LabelType::Finance)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);
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
