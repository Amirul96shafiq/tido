<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Helpers\MoneyDisplay;
use App\Models\Budget;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlySpendingOverview extends BaseWidget
{
    use InteractsWithDashboardMonth;

    public const SECTION_TOTAL_SPENT = 'total-spent';

    public const SECTION_SPENDING_FORECAST = 'spending-forecast';

    public const SECTION_SST_TAX_PAID = 'sst-tax-paid';

    public const SECTION_RECEIPTS_PROCESSED = 'receipts-processed';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    /**
     * @var array<string, int|string>
     */
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
    ];

    protected int|array|null $columns = [
        'default' => 1,
        'md' => 2,
    ];

    protected ?string $pollingInterval = null;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.stats-overview-with-section-id';

    protected function getPollingInterval(): ?string
    {
        return $this->isCurrentMonthSelected() ? '15s' : null;
    }

    protected function getStats(): array
    {
        $bounds = $this->getSelectedMonthBounds();
        $monthLabel = $this->formatSelectedMonth('F Y');
        $summary = $this->analytics()->summary();
        $trend = $this->analytics()->trend(6);
        $spendingChart = $trend['data'];
        $taxChart = $trend['tax_data'];
        $receiptsChart = array_map(
            static fn (int $count): float => (float) $count,
            $trend['receipt_counts'],
        );

        $thisMonthTotal = $summary['current_total'];
        $lastMonthTotal = $summary['previous_total'];
        $difference = $thisMonthTotal - $lastMonthTotal;
        $description = MoneyDisplay::withPrefix(abs($difference));

        if ($lastMonthTotal > 0) {
            $percent = ($difference / $lastMonthTotal) * 100;
            $description .= sprintf(' (%s%.1f%%)', $difference >= 0 ? '+' : '-', abs($percent));
        }

        $description .= ' vs '.$this->previousMonthLabel();

        $descriptionIcon = $difference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $descriptionColor = $difference >= 0 ? 'danger' : 'success';

        $thisMonthReceipts = $summary['processed_count'];
        $lastMonthReceipts = $summary['previous_processed_count'];
        $receiptsDifference = $thisMonthReceipts - $lastMonthReceipts;
        $receiptsDescription = (string) abs($receiptsDifference);

        if ($lastMonthReceipts > 0) {
            $receiptsPercent = ($receiptsDifference / $lastMonthReceipts) * 100;
            $receiptsDescription .= sprintf(' (%s%.1f%%)', $receiptsDifference >= 0 ? '+' : '-', abs($receiptsPercent));
        }

        $receiptsDescription .= ' vs '.$this->previousMonthLabel();
        $receiptsDescriptionIcon = $receiptsDifference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $receiptsDescriptionColor = $receiptsDifference >= 0 ? 'success' : 'warning';

        $stats = [
            Stat::make('Total Spent ('.$monthLabel.')', MoneyDisplay::withPrefix($thisMonthTotal))
                ->description($description)
                ->descriptionIcon($descriptionIcon)
                ->color($descriptionColor)
                ->chart($spendingChart)
                ->extraAttributes(['id' => self::SECTION_TOTAL_SPENT]),

            Stat::make('SST Tax Paid', MoneyDisplay::withPrefix($summary['current_tax']))
                ->description('Estimated 6% local taxation')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray')
                ->chart($taxChart)
                ->extraAttributes(['id' => self::SECTION_SST_TAX_PAID]),

            Stat::make('Receipts Processed ('.$monthLabel.')', (string) $thisMonthReceipts)
                ->description($receiptsDescription)
                ->descriptionIcon($receiptsDescriptionIcon)
                ->color($receiptsDescriptionColor)
                ->chart($receiptsChart)
                ->extraAttributes(['id' => self::SECTION_RECEIPTS_PROCESSED]),
        ];

        if ($this->isCurrentMonthSelected()) {
            $now = now();
            $currentDay = $now->day;
            $totalDays = $now->daysInMonth;
            $averageDailySpend = $currentDay > 0 ? $thisMonthTotal / $currentDay : 0;
            $remainingDays = $totalDays - $currentDay;
            $projectedSpend = $thisMonthTotal + ($averageDailySpend * $remainingDays);
            $forecastChart = $spendingChart;
            $forecastChart[count($forecastChart) - 1] = (float) $projectedSpend;

            $overallMonthlyBudget = Budget::whereNull('label_id')
                ->where('period', 'monthly')
                ->where('is_active', true)
                ->value('amount');

            $overallMonthlyBudget = $overallMonthlyBudget ? (float) $overallMonthlyBudget : null;

            $forecastDesc = 'Based on '.MoneyDisplay::withPrefix($averageDailySpend).' avg daily spend';
            $forecastColor = 'info';

            if ($overallMonthlyBudget) {
                $budgetStatus = ($projectedSpend / $overallMonthlyBudget) * 100;
                // Whole-percent display must not round a true exceed (e.g. 100.4%) back to 100%.
                $displayBudgetPercent = $budgetStatus > 100
                    ? (int) max(101, (int) round($budgetStatus))
                    : (int) round($budgetStatus);

                if ($budgetStatus > 100) {
                    $forecastDesc = sprintf('Projected to EXCEED budget (%d%%)', $displayBudgetPercent);
                    $forecastColor = 'danger';
                } else {
                    $forecastDesc = sprintf('Projected at %d%% of budget (%s)', $displayBudgetPercent, MoneyDisplay::withPrefix($overallMonthlyBudget));
                    $forecastColor = 'success';
                }
            }

            array_splice($stats, 1, 0, [
                Stat::make('Spending Forecast (End of Month)', MoneyDisplay::withPrefix($projectedSpend))
                    ->description($forecastDesc)
                    ->descriptionIcon('heroicon-m-chart-bar')
                    ->color($forecastColor)
                    ->chart($forecastChart)
                    ->extraAttributes(['id' => self::SECTION_SPENDING_FORECAST]),
            ]);
        } else {
            $daysInMonth = $bounds['start']->daysInMonth;
            $dailyAverage = $daysInMonth > 0 ? $thisMonthTotal / $daysInMonth : 0;
            $dailyAverageChart = array_map(
                fn (float $total, int $index): float => $total / $this->getSelectedMonth()
                    ->copy()
                    ->subMonths(count($spendingChart) - 1 - $index)
                    ->daysInMonth,
                $spendingChart,
                array_keys($spendingChart),
            );

            array_splice($stats, 1, 0, [
                Stat::make('Daily Average ('.$monthLabel.')', MoneyDisplay::withPrefix($dailyAverage))
                    ->description(sprintf('Across %d days in month', $daysInMonth))
                    ->descriptionIcon('heroicon-m-calculator')
                    ->color('info')
                    ->chart($dailyAverageChart)
                    ->extraAttributes(['id' => self::SECTION_SPENDING_FORECAST]),
            ]);
        }

        return $stats;
    }
}
