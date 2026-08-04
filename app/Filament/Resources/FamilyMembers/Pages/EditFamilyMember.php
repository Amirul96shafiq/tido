<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Pages;

use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\FamilyMembers\Schemas\FamilyMemberForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFamilyMember extends EditRecord
{
    use AppendsResourceLabelToEditTitle;
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft {
        afterSave as protected clearContentDraftAfterSave;
    }

    protected static string $resource = FamilyMemberResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-family-member-form-page',
        ];
    }

    protected function contentDraftKey(): string
    {
        return 'family-member-edit-'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->clearContentDraftAfterSave();
        $this->dispatch('family-member-updated', familyMemberId: $this->getRecord()->getKey());
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return FamilyMemberForm::sectionNavItems();
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Family member sections';
    }
}
