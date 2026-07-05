<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

class TopMerchants extends ChartWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;
    protected ?string $heading = 'Top Merchants (This Month)';
    
    public function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $now = now();
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $merchants = Invoice::whereBetween('date_time', [$start, $end])
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
