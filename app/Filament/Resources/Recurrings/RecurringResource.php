<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings;

use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Filament\Resources\Recurrings\Pages\ListRecurrings;
use App\Filament\Resources\Recurrings\Schemas\RecurringForm;
use App\Filament\Resources\Recurrings\Tables\RecurringsTable;
use App\Helpers\MoneyDisplay;
use App\Models\Recurring;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecurringResource extends Resource
{
    use RequiresPrimaryHouseholdAccess;

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

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['label', 'familyMember']);
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
}
