<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers;

use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\GlobalSearch\AppliesGlobalSearchCriteria;
use App\Filament\Resources\FamilyMembers\Pages\CreateFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\EditFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\ListFamilyMembers;
use App\Filament\Resources\FamilyMembers\Schemas\FamilyMemberForm;
use App\Filament\Resources\FamilyMembers\Tables\FamilyMembersTable;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyMemberDuplicator;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FamilyMemberResource extends Resource
{
    use RequiresPrimaryHouseholdAccess;

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

    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->modalHeading(fn (FamilyMember $record): string => 'Duplicate '.$record->name)
            ->modalDescription('A new WhatsApp number is required. Login, allowlist access, WhatsApp identity, and profile photo are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->schema([
                TextInput::make('phone')
                    ->label('New WhatsApp Number')
                    ->tel()
                    ->required()
                    ->placeholder('+60123456789')
                    ->maxLength(20)
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        $phone = PhoneNumber::normalize(is_string($value) ? $value : null);

                        if ($phone === null) {
                            $fail('Enter a valid Malaysian WhatsApp number (e.g. +60123456789, 60123456789, or 0123456789).');

                            return;
                        }

                        if (FamilyMember::withTrashed()->where('phone', $phone)->exists()) {
                            $fail('This WhatsApp number is already registered.');
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state): ?string => PhoneNumber::normalize($state)),
                Toggle::make('allowlist_enabled')
                    ->label('Include in contact allowlist')
                    ->default(false),
                Toggle::make('login_enabled')
                    ->label('Allow panel login via WhatsApp OTP')
                    ->default(false)
                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        if (! $value) {
                            return;
                        }

                        $phone = PhoneNumber::normalize($get('phone'));

                        if ($phone !== null && User::query()->where('phone', $phone)->exists()) {
                            $fail('This WhatsApp number is already used by an account.');
                        }
                    }),
            ])
            ->successNotificationTitle('Family member duplicated')
            ->action(function (array $data, FamilyMember $record, FamilyMemberDuplicator $duplicator, Action $action): void {
                $replica = $duplicator->duplicate($record, [
                    'phone' => (string) $data['phone'],
                    'allowlist_enabled' => (bool) $data['allowlist_enabled'],
                    'login_enabled' => (bool) $data['login_enabled'],
                ]);

                $action->successRedirectUrl(static::getUrl('edit', ['record' => $replica]));
            });
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
        ];
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        AppliesGlobalSearchCriteria::applyToFamilyMemberQuery($query);
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

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
