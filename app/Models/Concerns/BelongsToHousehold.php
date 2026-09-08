<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Household;
use App\Support\CurrentHousehold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 *
 * @property int $household_id
 * @property-read Household|null $household
 */
trait BelongsToHousehold
{
    public static function bootBelongsToHousehold(): void
    {
        static::addGlobalScope('household', function (Builder $builder): void {
            $householdId = CurrentHousehold::id();

            if ($householdId === null) {
                return;
            }

            $builder->where($builder->getModel()->getTable().'.household_id', $householdId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('household_id') !== null) {
                return;
            }

            $householdId = CurrentHousehold::id();

            if ($householdId !== null) {
                $model->setAttribute('household_id', $householdId);
            }
        });
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
