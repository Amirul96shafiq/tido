<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Schemas;

use App\Enums\FamilyRelationship;
use App\Support\PhoneNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

class FamilyMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(10)
            ->components([
                Grid::make(1)
                    ->columnSpan(7)
                    ->schema([
                        Section::make(fn (LivewireComponent $livewire): string => self::modelLabel($livewire).' Details')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Full Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Full name'),

                                TextInput::make('display_name')
                                    ->label('Display Name')
                                    ->maxLength(255)
                                    ->placeholder('Display name'),

                                TextInput::make('phone')
                                    ->label('WhatsApp Number')
                                    ->tel()
                                    ->required()
                                    ->placeholder('+60123456789')
                                    ->maxLength(20)
                                    ->unique(table: 'family_members', column: 'phone', ignoreRecord: true)
                                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        if (PhoneNumber::normalize(is_string($value) ? $value : null) === null) {
                                            $fail('Enter a valid Malaysian WhatsApp number (e.g. +60123456789, 60123456789, or 0123456789).');
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): ?string => PhoneNumber::normalize($state)),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('name@example.com'),

                                Select::make('relationship')
                                    ->label('Relationship')
                                    ->options(FamilyRelationship::options())
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->placeholder('Select relationship')
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if ($state !== FamilyRelationship::Other->value) {
                                            $set('relationship_other', null);
                                        }
                                    }),

                                TextInput::make('relationship_other')
                                    ->label('Custom relationship')
                                    ->maxLength(255)
                                    ->placeholder('Describe the relationship')
                                    ->visible(fn (Get $get): bool => $get('relationship') === FamilyRelationship::Other->value)
                                    ->required(fn (Get $get): bool => $get('relationship') === FamilyRelationship::Other->value)
                                    ->dehydrateStateUsing(function (?string $state, Get $get): ?string {
                                        if ($get('relationship') !== FamilyRelationship::Other->value) {
                                            return null;
                                        }

                                        return $state;
                                    }),

                                DatePicker::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->placeholder('DD/MM/YYYY'),

                                Toggle::make('allowlist_enabled')
                                    ->label('Include in contact allowlist')
                                    ->helperText('When enabled, this number can talk to the WhatsApp bot and send receipts.')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Profile Photo')
                    ->columnSpan(3)
                    ->extraAttributes(['class' => 'fi-profile-photo-section'])
                    ->schema([
                        Flex::make([
                            FileUpload::make('avatar_url')
                                ->hiddenLabel()
                                ->fieldWrapperView('filament-forms::plain-field-wrapper')
                                ->extraFieldWrapperAttributes(['class' => 'fi-profile-photo-field'])
                                ->avatar()
                                ->disk('public')
                                ->directory('avatars')
                                ->image()
                                ->imageEditor()
                                ->maxSize(2048)
                                ->circleCropper(),
                        ])->alignCenter(),
                    ]),
            ]);
    }

    protected static function modelLabel(LivewireComponent $livewire): string
    {
        if ($livewire instanceof ResourcePage) {
            return $livewire::getResource()::getTitleCaseModelLabel();
        }

        return 'Record';
    }
}
