<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\Labels\LabelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLabels extends ListRecords
{
    use PrependsHomeBreadcrumb;

    protected static string $resource = LabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
