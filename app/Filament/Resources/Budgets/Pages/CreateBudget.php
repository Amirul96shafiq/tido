<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Budgets\BudgetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBudget extends CreateRecord
{
    use HasStickyBlurFormActions;
    use RecoversContentDraft;

    protected static string $resource = BudgetResource::class;

    protected function contentDraftKey(): string
    {
        return 'budget-create';
    }
}
