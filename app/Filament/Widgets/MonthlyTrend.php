<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
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
        $trend = $this->analytics()->trend();
        $selectedIndex = $trend['selected_index'];
        $pointColors = array_map(
            fn (int $index): string => $index === $selectedIndex ? '#FFA524' : '#FFD07D',
            array_keys($trend['data']),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Spent (RM)',
                    'data' => $trend['data'],
                    'borderColor' => '#FFD07D',
                    'backgroundColor' => 'rgba(255, 208, 125, 0.1)',
                    'pointBackgroundColor' => $pointColors,
                    'pointBorderColor' => $pointColors,
                    'pointRadius' => array_map(
                        fn (int $index): int => $index === $selectedIndex ? 6 : 4,
                        array_keys($trend['data']),
                    ),
                    'fill' => true,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }
}
