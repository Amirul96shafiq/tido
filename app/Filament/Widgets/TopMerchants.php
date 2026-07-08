<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Invoice;
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
        $bounds = $this->getSelectedMonthBounds();

        $merchants = Invoice::whereBetween('date_time', [$bounds['start'], $bounds['end']])
            ->whereIn('status', ['parsed', 'reviewed'])
            ->selectRaw('merchant_name, SUM(total_amount) as total')
            ->groupBy('merchant_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $labels = $merchants->pluck('merchant_name')->toArray();
        $data = $merchants->pluck('total')->map(fn ($val) => (float) $val)->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total Spent (RM)',
                    'data' => $data,
                    'backgroundColor' => '#FFD07D',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
