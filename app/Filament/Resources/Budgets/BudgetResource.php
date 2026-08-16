<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets;

use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Budgets\Schemas\BudgetForm;
use App\Filament\Resources\Budgets\Tables\BudgetsTable;
use App\Helpers\MoneyDisplay;
use App\Models\Budget;
use App\Services\BudgetDuplicator;
use App\Support\HouseholdAccess;
use Filament\Actions\BulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BudgetResource extends Resource
{
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

    public static function duplicateAction(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Duplicate')
            ->requiresConfirmation()
            ->modalHeading(fn (Budget $record): string => 'Duplicate '.$record->display_title)
            ->modalDescription('Creates a new budget with the same settings. Spending totals are calculated from expenses and are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->successNotificationTitle('Budget duplicated')
            ->excludeAttributes(BudgetDuplicator::EXCLUDED_ATTRIBUTES)
            ->beforeReplicaSaved(function (Model $replica): void {
                /** @var Budget $replica */
                app(BudgetDuplicator::class)->prepareReplica($replica);
            })
            ->successRedirectUrl(fn (Model $replica): string => static::getUrl('edit', [
                'record' => $replica,
            ]))
            ->authorizationTooltip()
            ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage());
    }

    public static function duplicateBulkAction(): BulkAction
    {
        return BulkAction::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::Square2Stack)
            ->requiresConfirmation()
            ->modalHeading('Duplicate selected budgets')
            ->modalDescription('Creates new budgets with the same settings. Spending totals are calculated from expenses and are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->authorize('create')
            ->authorizationTooltip()
            ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage())
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle(function (Collection $records): string {
                $count = $records->count();

                return $count === 1
                    ? '1 budget duplicated'
                    : "{$count} budgets duplicated";
            })
            ->action(function (Collection $records, BudgetDuplicator $duplicator): void {
                $duplicator->duplicateMany($records);
            });
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

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        if (static::canEdit($record)) {
            return static::getUrl('edit', ['record' => $record]);
        }

        return static::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $record->getRouteKey(),
        ]);
    }
}
