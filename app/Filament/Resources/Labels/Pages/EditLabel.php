<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Pages;

use App\Filament\Resources\Labels\LabelResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLabel extends EditRecord
{
    protected static string $resource = LabelResource::class;

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
