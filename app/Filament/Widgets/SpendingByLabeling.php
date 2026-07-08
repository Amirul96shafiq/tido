<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class SpendingByLabeling extends ChartWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Spending by Labeling ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $spending = $this->analytics()->spentByLabeling();

        $labels = $spending->pluck('name')->toArray();
        $data = $spending->pluck('total')->toArray();
        $colors = $spending->pluck('color')->map(fn ($color) => $color ?: '#cccccc')->toArray();

        if (empty($data)) {
            $labels = ['No Expenses'];
            $data = [0];
            $colors = ['#e5e7eb'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Spent (RM)',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
