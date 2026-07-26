<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Schemas\LabelForm;
use Filament\Resources\Pages\CreateRecord;

class CreateLabel extends CreateRecord
{
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft;

    protected static string $resource = LabelResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-label-form-page',
        ];
    }

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

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return LabelForm::sectionNavItems();
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Label sections';
    }
}
