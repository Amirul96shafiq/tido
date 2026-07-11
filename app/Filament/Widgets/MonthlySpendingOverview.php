<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Budget;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlySpendingOverview extends BaseWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getPollingInterval(): ?string
    {
        return $this->isCurrentMonthSelected() ? '15s' : null;
    }

    protected function getStats(): array
    {
        $bounds = $this->getSelectedMonthBounds();
        $monthLabel = $this->formatSelectedMonth('F Y');
        $summary = $this->analytics()->summary();

        $thisMonthTotal = $summary['current_total'];
        $lastMonthTotal = $summary['previous_total'];
        $difference = $thisMonthTotal - $lastMonthTotal;
        $description = 'RM '.number_format(abs($difference), 2);

        if ($lastMonthTotal > 0) {
            $percent = ($difference / $lastMonthTotal) * 100;
            $description .= sprintf(' (%s%.1f%%)', $difference >= 0 ? '+' : '-', abs($percent));
        }

        $description .= ' vs '.$this->previousMonthLabel();

        $descriptionIcon = $difference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $descriptionColor = $difference >= 0 ? 'danger' : 'success';

        $stats = [
            Stat::make('Total Spent ('.$monthLabel.')', 'RM '.number_format($thisMonthTotal, 2))
                ->description($description)
                ->descriptionIcon($descriptionIcon)
                ->color($descriptionColor),

            Stat::make('SST Tax Paid', 'RM '.number_format($summary['current_tax'], 2))
                ->description('Estimated 6% local taxation')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),

            Stat::make('Receipts Processed', (string) $summary['processed_count'])
                ->description($summary['pending_count'].' pending parsing')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($summary['pending_count'] > 0 ? 'warning' : 'success'),
        ];

        if ($this->isCurrentMonthSelected()) {
            $now = now();
            $currentDay = $now->day;
            $totalDays = $now->daysInMonth;
            $averageDailySpend = $currentDay > 0 ? $thisMonthTotal / $currentDay : 0;
            $remainingDays = $totalDays - $currentDay;
            $projectedSpend = $thisMonthTotal + ($averageDailySpend * $remainingDays);

            $overallMonthlyBudget = Budget::whereNull('label_id')
                ->where('period', 'monthly')
                ->where('is_active', true)
                ->value('amount');

            $overallMonthlyBudget = $overallMonthlyBudget ? (float) $overallMonthlyBudget : null;

            $forecastDesc = sprintf('Based on RM %.2f avg daily spend', $averageDailySpend);
            $forecastColor = 'info';

            if ($overallMonthlyBudget) {
                $budgetStatus = ($projectedSpend / $overallMonthlyBudget) * 100;

                if ($budgetStatus > 100) {
                    $forecastDesc = sprintf('Projected to EXCEED budget (%.0f%%)', $budgetStatus);
                    $forecastColor = 'danger';
                } else {
                    $forecastDesc = sprintf('Projected at %.0f%% of budget (RM %.2f)', $budgetStatus, $overallMonthlyBudget);
                    $forecastColor = 'success';
                }
            }

            array_splice($stats, 1, 0, [
                Stat::make('Spending Forecast (End of Month)', 'RM '.number_format($projectedSpend, 2))
                    ->description($forecastDesc)
                    ->descriptionIcon('heroicon-m-chart-bar')
                    ->color($forecastColor),
            ]);
        } else {
            $daysInMonth = $bounds['start']->daysInMonth;
            $dailyAverage = $daysInMonth > 0 ? $thisMonthTotal / $daysInMonth : 0;

            array_splice($stats, 1, 0, [
                Stat::make('Daily Average ('.$monthLabel.')', 'RM '.number_format($dailyAverage, 2))
                    ->description(sprintf('Across %d days in month', $daysInMonth))
                    ->descriptionIcon('heroicon-m-calculator')
                    ->color('info'),
            ]);
        }

        return $stats;
    }
}
