<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Helpers\MoneyDisplay;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final class MoneyStateCast implements StateCast
{
    public function get(mixed $state): ?float
    {
        return MoneyDisplay::parse($state);
    }

    public function set(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        return MoneyDisplay::format($state);
    }
}
