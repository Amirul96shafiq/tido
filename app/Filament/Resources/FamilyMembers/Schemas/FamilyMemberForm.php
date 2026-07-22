<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Schemas;

use App\Enums\FamilyRelationship;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
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
use Filament\Support\Icons\Heroicon;
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

                                TextInput::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->mask('99/99/9999')
                                    ->placeholder('DD/MM/YYYY')
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }

                                        if ($state instanceof CarbonInterface) {
                                            return $state->format('d/m/Y');
                                        }

                                        if (! is_string($state)) {
                                            return null;
                                        }

                                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $state) === 1) {
                                            return $state;
                                        }

                                        try {
                                            return Carbon::parse($state)->format('d/m/Y');
                                        } catch (\Throwable) {
                                            return $state;
                                        }
                                    })
                                    ->dehydrateStateUsing(function (?string $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }

                                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $state) !== 1) {
                                            return null;
                                        }

                                        try {
                                            $date = Carbon::createFromFormat('!d/m/Y', $state);
                                        } catch (\Throwable) {
                                            return null;
                                        }

                                        if ($date === false || $date->format('d/m/Y') !== $state) {
                                            return null;
                                        }

                                        return $date->format('Y-m-d');
                                    })
                                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        if (! is_string($value) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) !== 1) {
                                            $fail('Enter a valid date as DD/MM/YYYY.');

                                            return;
                                        }

                                        try {
                                            $date = Carbon::createFromFormat('!d/m/Y', $value);
                                        } catch (\Throwable) {
                                            $fail('Enter a valid date as DD/MM/YYYY.');

                                            return;
                                        }

                                        if ($date === false || $date->format('d/m/Y') !== $value) {
                                            $fail('Enter a valid date as DD/MM/YYYY.');

                                            return;
                                        }

                                        if ($date->isFuture()) {
                                            $fail('Date of birth cannot be in the future.');
                                        }
                                    })
                                    ->suffixAction(
                                        Action::make('pickDateOfBirth')
                                            ->icon(Heroicon::CalendarDays)
                                            ->tooltip('Open calendar')
                                            ->modalWidth('sm')
                                            ->modalHeading('Date of Birth')
                                            ->modalSubmitActionLabel('Select')
                                            ->fillForm(function (Get $get): array {
                                                $current = $get('date_of_birth');
                                                $picked = null;

                                                if (is_string($current) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $current) === 1) {
                                                    try {
                                                        $date = Carbon::createFromFormat('!d/m/Y', $current);

                                                        if ($date !== false && $date->format('d/m/Y') === $current) {
                                                            $picked = $date->format('Y-m-d');
                                                        }
                                                    } catch (\Throwable) {
                                                        $picked = null;
                                                    }
                                                }

                                                return ['picked' => $picked];
                                            })
                                            ->schema([
                                                DatePicker::make('picked')
                                                    ->hiddenLabel()
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->maxDate(now())
                                                    ->required(),
                                            ])
                                            ->action(function (array $data, Set $set): void {
                                                if (blank($data['picked'] ?? null)) {
                                                    return;
                                                }

                                                $set(
                                                    'date_of_birth',
                                                    Carbon::parse((string) $data['picked'])->format('d/m/Y'),
                                                );
                                            }),
                                    ),

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
