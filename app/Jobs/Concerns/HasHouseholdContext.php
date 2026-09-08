<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Models\Household;
use App\Support\CurrentHousehold;
use RuntimeException;

trait HasHouseholdContext
{
    public int $householdId;

    protected function resolveHouseholdId(?int $householdId = null): int
    {
        $householdId ??= CurrentHousehold::id();
        $householdId ??= auth()->user()?->household_id;

        if ($householdId === null) {
            $householdIds = Household::query()->limit(2)->pluck('id');

            if ($householdIds->count() === 1) {
                $householdId = (int) $householdIds->first();
            }
        }

        if ($householdId === null) {
            throw new RuntimeException('A household is required for WhatsApp jobs.');
        }

        return $householdId;
    }
}
