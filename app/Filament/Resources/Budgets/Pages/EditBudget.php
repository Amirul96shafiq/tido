<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Budgets\BudgetResource;
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
}
