<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\FamilyMembers\Schemas\FamilyMemberForm;
use Filament\Resources\Pages\CreateRecord;

class CreateFamilyMember extends CreateRecord
{
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft;

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
        return 'family-member-create';
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
