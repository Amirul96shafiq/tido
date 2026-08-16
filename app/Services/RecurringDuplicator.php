<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Recurring;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringDuplicator
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

    public function __construct(
        private RecurringOccurrenceGenerator $generator,
    ) {}

    public function duplicate(Recurring $source): Recurring
    {
        $replica = $source->replicate(self::EXCLUDED_ATTRIBUTES);
        $this->prepareReplica($replica);
        $replica->save();

        $saved = $replica->fresh() ?? $replica;
        $this->afterSaved($saved);

        return $saved;
    }

    /**
     * @param  Collection<int, Recurring>  $sources
     * @return Collection<int, Recurring>
     */
    public function duplicateMany(Collection $sources): Collection
    {
        return DB::transaction(function () use ($sources): Collection {
            return $sources
                ->map(fn (Recurring $source): Recurring => $this->duplicate($source))
                ->values();
        });
    }

    public function prepareReplica(Recurring $replica): void
    {
        $replica->starts_on = now()->toDateString();
        $replica->next_due_on = null;
        $replica->sort_order = 0;
        $replica->prior_contributed_amount = null;

        if ($replica->instalment_total !== null) {
            $replica->instalment_remaining = $replica->instalment_total;
        }
    }

    public function afterSaved(Recurring $replica): void
    {
        $this->generator->generateFor($replica);
    }
}
