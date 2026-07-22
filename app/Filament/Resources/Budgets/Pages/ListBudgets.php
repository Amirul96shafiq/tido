<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\Budgets\BudgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBudgets extends ListRecords
{
    use PrependsHomeBreadcrumb;

    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
