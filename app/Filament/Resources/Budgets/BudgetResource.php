<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets;

use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Budgets\Schemas\BudgetForm;
use App\Filament\Resources\Budgets\Tables\BudgetsTable;
use App\Helpers\MoneyDisplay;
use App\Models\Budget;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BudgetResource extends Resource
{
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $model = Budget::class;

    protected static ?string $recordTitleAttribute = 'global_search_title';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return BudgetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgets::route('/'),
            'create' => CreateBudget::route('/create'),
            'edit' => EditBudget::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'title',
            'label.name',
            'period',
            'year',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['label', 'familyMember', 'editedBy']);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['label', 'familyMember']);
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Amount' => MoneyDisplay::withPrefix($record->amount),
            'Period' => ucfirst((string) $record->period),
            'Active' => $record->is_active ? 'Yes' : 'No',
        ];
    }
}
