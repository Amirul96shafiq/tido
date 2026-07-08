<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class TopMerchants extends ChartWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Top Merchants ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $merchants = $this->analytics()->topMerchants();

        return [
            'datasets' => [
                [
                    'label' => 'Total Spent (RM)',
                    'data' => $merchants->pluck('total')->toArray(),
                    'backgroundColor' => '#FFD07D',
                ],
            ],
            'labels' => $merchants->pluck('merchant_name')->toArray(),
        ];
    }
}
