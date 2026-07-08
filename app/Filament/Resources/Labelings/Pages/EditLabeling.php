<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labelings\Pages;

use App\Filament\Resources\Labelings\LabelingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLabeling extends EditRecord
{
    protected static string $resource = LabelingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! (bool) $this->record->is_system),
            ForceDeleteAction::make()
                ->visible(fn () => ! (bool) $this->record->is_system),
            RestoreAction::make(),
        ];
    }
}
