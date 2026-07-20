<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'is_system' => false,
    ];

    protected $fillable = [
        'name',
        'slug',
        'aliases',
        'notes',
        'icon',
        'color',
        'is_system',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_system' => 'boolean',
    ];

    /**
     * @return Collection<int, self>
     */
    public static function orderedForSelect(): Collection
    {
        return static::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'aliases', 'notes', 'icon', 'color']);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
