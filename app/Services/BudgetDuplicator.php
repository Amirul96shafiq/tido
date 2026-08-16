<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Budget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetDuplicator
{
    /**
     * Attributes that must not carry over to the replica.
     *
     * @var list<string>
     */
    public const EXCLUDED_ATTRIBUTES = [
        'edited_by',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    public function duplicate(Budget $source): Budget
    {
        $replica = $source->replicate(self::EXCLUDED_ATTRIBUTES);
        $this->prepareReplica($replica);
        $replica->save();

        return $replica->fresh() ?? $replica;
    }

    /**
     * @param  Collection<int, Budget>  $sources
     * @return Collection<int, Budget>
     */
    public function duplicateMany(Collection $sources): Collection
    {
        return DB::transaction(function () use ($sources): Collection {
            return $sources
                ->map(fn (Budget $source): Budget => $this->duplicate($source))
                ->values();
        });
    }

    public function prepareReplica(Budget $replica): void
    {
        // Budget::creating only assigns the next sort_order when the value is null.
        $replica->sort_order = null;
    }
}
