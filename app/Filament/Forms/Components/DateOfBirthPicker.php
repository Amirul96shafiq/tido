<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\DatePicker;

/**
 * Shared date-of-birth field for Profile and Family Members.
 */
class DateOfBirthPicker extends DatePicker
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'date_of_birth');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Date of Birth')
            ->maxDate(now());
    }
}
