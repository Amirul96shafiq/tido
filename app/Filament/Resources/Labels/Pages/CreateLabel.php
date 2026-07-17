<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Labels\LabelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLabel extends CreateRecord
{
    use HasStickyBlurFormActions;
    use RecoversContentDraft;

    protected static string $resource = LabelResource::class;

    protected function contentDraftKey(): string
    {
        return 'label-create';
    }

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
