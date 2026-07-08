<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labelings\Pages;

use App\Filament\Resources\Labelings\LabelingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLabelings extends ListRecords
{
    protected static string $resource = LabelingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
