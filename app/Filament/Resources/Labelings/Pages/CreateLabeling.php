<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labelings\Pages;

use App\Filament\Resources\Labelings\LabelingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLabeling extends CreateRecord
{
    protected static string $resource = LabelingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_system'] = false;

        return $data;
    }
}
