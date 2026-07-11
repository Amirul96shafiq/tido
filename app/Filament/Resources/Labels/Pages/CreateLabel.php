<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Pages;

use App\Filament\Resources\Labels\LabelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLabel extends CreateRecord
{
    protected static string $resource = LabelResource::class;

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
