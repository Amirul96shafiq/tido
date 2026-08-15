<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Schemas\BudgetForm;
use App\Models\Budget;
use App\Support\HouseholdAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBudget extends EditRecord
{
    use AppendsResourceLabelToEditTitle;
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft;

    protected static string $resource = BudgetResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-budget-form-page',
        ];
    }

    protected function contentDraftKey(): string
    {
        return 'budget-edit-'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return BudgetForm::sectionNavItems(includePerformance: true);
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Budget sections';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! HouseholdAccess::isFamilyMember()) {
            return $data;
        }

        /** @var Budget $record */
        $record = $this->getRecord();
        $data['family_member_id'] = $record->family_member_id;
        $data['is_shared'] = $record->is_shared;

        return $data;
    }
}
