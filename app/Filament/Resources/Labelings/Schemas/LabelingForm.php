<?php

declare(strict_types=1);

namespace App\Filament\Resources\Labelings\Schemas;

use App\Enums\LabelingType;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class LabelingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                TextInput::make('icon')
                    ->placeholder('heroicon-o-cake'),

                ColorPicker::make('color'),
            ]);
    }
}
