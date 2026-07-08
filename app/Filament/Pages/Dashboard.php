<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Support\DashboardMonthPeriod;
use Filament\Actions\Action;
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
            Action::make('previousMonth')
                ->label('Previous month')
                ->icon('heroicon-m-chevron-left')
                ->action(function (): void {
                    $this->filters = [
                        'month' => DashboardMonthPeriod::fromFilters($this->filters)
                            ->copy()
                            ->subMonth()
                            ->format('Y-m'),
                    ];

                    $this->updatedFilters();
                }),

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

            Action::make('nextMonth')
                ->label('Next month')
                ->icon('heroicon-m-chevron-right')
                ->disabled(fn (): bool => DashboardMonthPeriod::isCurrentMonth(
                    DashboardMonthPeriod::fromFilters($this->filters),
                ))
                ->action(function (): void {
                    $this->filters = [
                        'month' => DashboardMonthPeriod::fromFilters($this->filters)
                            ->copy()
                            ->addMonth()
                            ->format('Y-m'),
                    ];

                    $this->updatedFilters();
                }),
        ];
    }
}
