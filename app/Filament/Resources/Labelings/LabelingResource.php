<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labelings;

use App\Filament\Resources\Labelings\Pages\CreateLabeling;
use App\Filament\Resources\Labelings\Pages\EditLabeling;
use App\Filament\Resources\Labelings\Pages\ListLabelings;
use App\Filament\Resources\Labelings\Schemas\LabelingForm;
use App\Filament\Resources\Labelings\Tables\LabelingsTable;
use App\Models\Labeling;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LabelingResource extends Resource
{
    protected static ?string $model = Labeling::class;

    protected static ?string $slug = 'labels';

    protected static ?string $navigationLabel = 'Labels';

    protected static ?string $modelLabel = 'Label';

    protected static ?string $pluralModelLabel = 'Labels';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return LabelingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LabelingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabelings::route('/'),
            'create' => CreateLabeling::route('/create'),
            'edit' => EditLabeling::route('/{record}/edit'),
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
