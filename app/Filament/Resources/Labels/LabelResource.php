<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labels;

use App\Enums\LabelType;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\GlobalSearch\AppliesGlobalSearchCriteria;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Filament\Resources\Labels\Schemas\LabelForm;
use App\Filament\Resources\Labels\Tables\LabelsTable;
use App\Models\Label;
use App\Services\LabelDuplicator;
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

class LabelResource extends Resource
{
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $model = Label::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 2;

    protected static ?string $slug = 'labels';

    protected static ?string $navigationLabel = 'Labels';

    protected static ?string $modelLabel = 'Label';

    protected static ?string $pluralModelLabel = 'Labels';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return LabelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LabelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabels::route('/'),
            'create' => CreateLabel::route('/create'),
            'edit' => EditLabel::route('/{record}/edit'),
        ];
    }

    public static function duplicateAction(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Duplicate')
            ->requiresConfirmation()
            ->modalHeading(fn (Label $record): string => 'Duplicate '.$record->name)
            ->modalDescription('Creates a user label with the same appearance and notes. Expenses and budgets are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->successNotificationTitle('Label duplicated')
            ->excludeAttributes(LabelDuplicator::EXCLUDED_ATTRIBUTES)
            ->beforeReplicaSaved(function (Model $replica): void {
                /** @var Label $replica */
                app(LabelDuplicator::class)->prepareReplica($replica);
            })
            ->successRedirectUrl(fn (Model $replica): string => static::getUrl('edit', [
                'record' => $replica,
            ]));
    }

    public static function duplicateBulkAction(): BulkAction
    {
        return BulkAction::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::Square2Stack)
            ->requiresConfirmation()
            ->modalHeading('Duplicate selected labels')
            ->modalDescription('Creates user labels with the same appearance and notes. Expenses and budgets are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle(function (Collection $records): string {
                $count = $records->count();

                return $count === 1
                    ? '1 label duplicated'
                    : "{$count} labels duplicated";
            })
            ->action(function (Collection $records, LabelDuplicator $duplicator): void {
                $duplicator->duplicateMany($records);
            });
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'slug',
            'description',
        ];
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        AppliesGlobalSearchCriteria::applyToLabelQuery($query);
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Type' => $record->type instanceof LabelType ? $record->type->label() : (string) $record->type,
            'Slug' => $record->slug,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
