<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Schemas;

use App\Support\PhoneNumber;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

class FamilyMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile Photo')
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

                Section::make(fn (LivewireComponent $livewire): string => self::modelLabel($livewire).' Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Family member name'),

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

                        Toggle::make('allowlist_enabled')
                            ->label('Include in contact allowlist')
                            ->helperText('When enabled, this number can talk to the WhatsApp bot and send receipts.')
                            ->default(true)
                            ->columnSpanFull(),
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
