<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Support\DashboardMonthPeriod;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static bool $shouldRegisterNavigation = false;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label(fn (): string => DashboardMonthPeriod::labelFromFilters($this->filters))
                ->schema([
                    Select::make('month')
                        ->label('Month')
                        ->options(DashboardMonthPeriod::options())
                        ->default(now()->format('Y-m'))
                        ->required()
                        ->native(false),
                ]),
        ];
    }
}
