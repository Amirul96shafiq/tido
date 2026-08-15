<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\Recurrings\RecurringResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurrings extends ListRecords
{
    use PrependsHomeBreadcrumb;

    protected static string $resource = RecurringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->authorizationTooltip(),
        ];
    }
}
