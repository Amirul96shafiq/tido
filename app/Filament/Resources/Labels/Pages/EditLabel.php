<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels\Pages;

use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Schemas\LabelForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLabel extends EditRecord
{
    use AppendsResourceLabelToEditTitle;
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
        return 'label-edit-'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! (bool) $this->record->is_system),
            RestoreAction::make(),
        ];
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
