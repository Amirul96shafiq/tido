<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers;

use App\Filament\Resources\FamilyMembers\Pages\CreateFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\EditFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\ListFamilyMembers;
use App\Filament\Resources\FamilyMembers\Schemas\FamilyMemberForm;
use App\Filament\Resources\FamilyMembers\Tables\FamilyMembersTable;
use App\Models\FamilyMember;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FamilyMemberResource extends Resource
{
    protected static ?string $model = FamilyMember::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 4;

    protected static ?string $slug = 'family-members';

    protected static ?string $navigationLabel = 'Family Members';

    protected static ?string $modelLabel = 'Family Member';

    protected static ?string $pluralModelLabel = 'Family Members';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return FamilyMemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FamilyMembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFamilyMembers::route('/'),
            'create' => CreateFamilyMember::route('/create'),
            'edit' => EditFamilyMember::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'display_name',
            'phone',
            'email',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var FamilyMember $record */
        $details = [
            'WhatsApp' => (string) $record->phone,
        ];

        if (filled($record->display_name)) {
            $details['Display'] = (string) $record->display_name;
        }

        return $details;
    }
}
