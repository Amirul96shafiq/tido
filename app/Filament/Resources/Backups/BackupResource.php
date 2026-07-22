<?php

declare(strict_types=1);

namespace App\Filament\Resources\Backups;

use App\Enums\BackupType;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Filament\Resources\Backups\Tables\BackupsTable;
use App\Models\Backup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BackupResource extends Resource
{
    protected static ?string $model = Backup::class;

    protected static ?string $recordTitleAttribute = 'filename';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 5;

    protected static ?string $slug = 'backups';

    protected static ?string $navigationLabel = 'Backups';

    protected static ?string $modelLabel = 'Backup';

    protected static ?string $pluralModelLabel = 'Backups';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return BackupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBackups::route('/'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'filename',
            'type',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Type' => $record->type instanceof BackupType ? $record->type->label() : (string) $record->type,
            'Size' => $record->formattedSize(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('index');
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
