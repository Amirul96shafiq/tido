<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Budget;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlySpendingOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $now = now();
        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();

        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $thisMonthTotal = (float) Invoice::whereBetween('date_time', [$thisMonthStart, $thisMonthEnd])
            ->whereIn('status', ['parsed', 'reviewed'])
            ->sum('total_amount');

        $lastMonthTotal = (float) Invoice::whereBetween('date_time', [$lastMonthStart, $lastMonthEnd])
            ->whereIn('status', ['parsed', 'reviewed'])
            ->sum('total_amount');

        $difference = $thisMonthTotal - $lastMonthTotal;
        $description = 'RM '.number_format(abs($difference), 2);

        if ($lastMonthTotal > 0) {
            $percent = ($difference / $lastMonthTotal) * 100;
            $description .= sprintf(' (%s%.1f%%)', $difference >= 0 ? '+' : '-', abs($percent));
        } else {
            $description .= ' vs last month';
        }

        $descriptionIcon = $difference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $descriptionColor = $difference >= 0 ? 'danger' : 'success';

        $thisMonthTax = (float) Invoice::whereBetween('date_time', [$thisMonthStart, $thisMonthEnd])
            ->sum('total_tax');

        $pendingCount = Invoice::where('status', 'pending')->count();
        $processedCount = Invoice::whereIn('status', ['parsed', 'reviewed'])->count();

        // Forecast logic
        $currentDay = $now->day;
        $totalDays = $now->daysInMonth;

        $averageDailySpend = $currentDay > 0 ? $thisMonthTotal / $currentDay : 0;
        $remainingDays = $totalDays - $currentDay;

        $projectedSpend = $thisMonthTotal + ($averageDailySpend * $remainingDays);

        $overallMonthlyBudget = Budget::whereNull('labeling_id')
            ->where('period', 'monthly')
            ->where('is_active', true)
            ->value('amount');

        $overallMonthlyBudget = $overallMonthlyBudget ? (float) $overallMonthlyBudget : null;

        $forecastDesc = sprintf('Based on RM %.2f avg daily spend', $averageDailySpend);
        $forecastColor = 'info';

        if ($overallMonthlyBudget) {
            $budgetStatus = ($projectedSpend / $overallMonthlyBudget) * 100;
            if ($budgetStatus > 100) {
                $forecastDesc = sprintf('⚠️ Projected to EXCEED budget (%.0f%%)', $budgetStatus);
                $forecastColor = 'danger';
            } else {
                $forecastDesc = sprintf('Projected at %.0f%% of budget (RM %.2f)', $budgetStatus, $overallMonthlyBudget);
                $forecastColor = 'success';
            }
        }

        return [
            Stat::make('Total Spent (This Month)', 'RM '.number_format($thisMonthTotal, 2))
                ->description($description)
                ->descriptionIcon($descriptionIcon)
                ->color($descriptionColor),

            Stat::make('Spending Forecast (End of Month)', 'RM '.number_format($projectedSpend, 2))
                ->description($forecastDesc)
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($forecastColor),

            Stat::make('SST Tax Paid', 'RM '.number_format($thisMonthTax, 2))
                ->description('Estimated 6% local taxation')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),

            Stat::make('Receipts Processed', (string) $processedCount)
                ->description($pendingCount.' pending parsing')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($pendingCount > 0 ? 'warning' : 'success'),
        ];
    }
}
