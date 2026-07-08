<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class MonthlyTrend extends ChartWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Monthly Spending Trend (6 months to '.$this->formatSelectedMonth().')';
    }

    public function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = [];
        $data = [];
        $endMonth = $this->getSelectedMonth();

        for ($i = 5; $i >= 0; $i--) {
            $month = $endMonth->copy()->subMonths($i);
            $months[] = $month->format('M Y');

            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $total = Invoice::whereBetween('date_time', [$start, $end])
                ->whereIn('status', ['parsed', 'reviewed'])
                ->sum('total_amount');

            $data[] = (float) $total;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Spent (RM)',
                    'data' => $data,
                    'borderColor' => '#FFD07D',
                    'backgroundColor' => 'rgba(255, 208, 125, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $months,
        ];
    }
}
