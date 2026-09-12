<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Resources\Pages\ListRecords;
use Livewire\Component;

final class FormSchemaDeferral
{
    public static function unlessViewSlideOver(): Closure
    {
        return fn (Component $livewire): bool => ! $livewire instanceof ListRecords;
    }
}
