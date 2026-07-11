<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labelings\Schemas;

use App\Enums\LabelingType;
use App\Filament\Forms\Components\IconPicker;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component as LivewireComponent;

class LabelingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(fn (LivewireComponent $livewire): string => self::modelLabel($livewire).' Appearance')
                    ->columnSpan(1)
                    ->schema([
                        View::make('filament.forms.components.labeling-icon-preview')
                            ->viewData(fn (Get $get, LivewireComponent $livewire): array => [
                                'icon' => filled($get('icon')) ? (string) $get('icon') : 'heroicon-o-tag',
                                'color' => filled($get('color')) ? (string) $get('color') : '#a1a1aa',
                                'name' => filled($get('name'))
                                    ? (string) $get('name')
                                    : self::modelLabel($livewire).' preview',
                            ]),

                        IconPicker::make('icon')
                            ->label('Icon')
                            ->live(),

                        ColorPicker::make('color')
                            ->live(),
                    ]),

                Section::make(fn (LivewireComponent $livewire): string => self::modelLabel($livewire).' Details')
                    ->columnSpan(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(LabelingType::options())
                            ->default(LabelingType::Finance)
                            ->required()
                            ->disabled(fn ($record) => (bool) ($record?->is_system ?? false))
                            ->native(false),

                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->disabled(fn ($record) => (bool) ($record?->is_system ?? false))
                            ->unique(
                                table: 'labelings',
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('type', $get('type')),
                            ),
                            
                            Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
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
