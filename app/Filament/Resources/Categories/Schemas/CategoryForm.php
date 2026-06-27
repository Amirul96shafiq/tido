<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state ?? ''))),
                
                TextInput::make('slug')
                    ->required()
                    ->disabled(fn ($record) => (bool) ($record?->is_system ?? false))
                    ->unique('categories', 'slug', ignoreRecord: true),
                
                TextInput::make('icon')
                    ->placeholder('heroicon-o-cake'),
                
                ColorPicker::make('color'),
            ]);
    }
}
