<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Schemas;

use App\Enums\LabelingType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('labeling_id')
                    ->label('Labeling')
                    ->relationship(
                        name: 'labeling',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('type', LabelingType::Finance),
                    )
                    ->placeholder('Overall (All Labelings)')
                    ->searchable()
                    ->preload(),

                TextInput::make('amount')
                    ->numeric()
                    ->prefix('RM')
                    ->required(),

                Select::make('period')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                    ])
                    ->default('monthly')
                    ->live()
                    ->required(),

                Select::make('quarter')
                    ->options([
                        1 => 'Q1 (Jan - Mar)',
                        2 => 'Q2 (Apr - Jun)',
                        3 => 'Q3 (Jul - Sep)',
                        4 => 'Q4 (Oct - Dec)',
                    ])
                    ->visible(fn (callable $get): bool => $get('period') === 'quarterly')
                    ->required(fn (callable $get): bool => $get('period') === 'quarterly'),

                TextInput::make('year')
                    ->numeric()
                    ->default((int) date('Y'))
                    ->required(),

                Slider::make('alert_threshold')
                    ->label('Alert Threshold (%)')
                    ->minValue(10)
                    ->maxValue(100)
                    ->step(5)
                    ->default(80)
                    ->required(),

                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
