<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings;

use App\Filament\GlobalSearch\AppliesGlobalSearchCriteria;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Filament\Resources\Recurrings\Pages\ListRecurrings;
use App\Filament\Resources\Recurrings\Schemas\RecurringForm;
use App\Filament\Resources\Recurrings\Tables\RecurringsTable;
use App\Helpers\MoneyDisplay;
use App\Models\Recurring;
use App\Services\RecurringDuplicator;
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
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecurringResource extends Resource
{
    protected static ?string $model = Recurring::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Recurrings';

    protected static ?string $modelLabel = 'Recurring';

    protected static ?string $pluralModelLabel = 'Recurrings';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return RecurringForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurrings::route('/'),
            'create' => CreateRecurring::route('/create'),
            'edit' => EditRecurring::route('/{record}/edit'),
        ];
    }

    public static function duplicateAction(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Duplicate')
            ->requiresConfirmation()
            ->modalHeading(fn (Recurring $record): string => 'Duplicate '.$record->title)
            ->modalDescription('Creates a new template from this recurring. Occurrence history is not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->successNotificationTitle('Recurring duplicated')
            ->excludeAttributes(RecurringDuplicator::EXCLUDED_ATTRIBUTES)
            ->beforeReplicaSaved(function (Model $replica): void {
                /** @var Recurring $replica */
                app(RecurringDuplicator::class)->prepareReplica($replica);
            })
            ->after(function (Model $replica): void {
                /** @var Recurring $replica */
                app(RecurringDuplicator::class)->afterSaved($replica);
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
            ->modalHeading('Duplicate selected recurrings')
            ->modalDescription('Creates new templates from the selection. Occurrence history is not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->authorize('create')
            ->authorizationTooltip()
            ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage())
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle(function (Collection $records): string {
                $count = $records->count();

                return $count === 1
                    ? '1 recurring duplicated'
                    : "{$count} recurrings duplicated";
            })
            ->action(function (Collection $records, RecurringDuplicator $duplicator): void {
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
            'type',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'label',
            'familyMember',
            'editedBy',
            'occurrences' => fn ($query) => $query->open()->orderBy('due_on')->orderBy('id'),
        ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['label', 'familyMember']);
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        AppliesGlobalSearchCriteria::applyToRecurringQuery($query);
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Recurring $record */
        return [
            'Type' => $record->type->label(),
            'Amount' => $record->expected_amount !== null
                ? MoneyDisplay::withPrefix($record->expected_amount)
                : 'Variable',
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
