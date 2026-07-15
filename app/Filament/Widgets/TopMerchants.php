<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class TopMerchants extends ChartWidget
{
    use InteractsWithDashboardMonth;

    private const LABEL_LIMIT = 10;

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
        $names = $merchants->pluck('merchant_name');

        return [
            'datasets' => [
                [
                    'label' => 'Total Spent (RM)',
                    'data' => $merchants->pluck('total')->toArray(),
                    'backgroundColor' => '#FFD07D',
                    'merchantNames' => $names->toArray(),
                ],
            ],
            'labels' => $names
                ->map(fn (string $name): string => Str::limit($name, self::LABEL_LIMIT))
                ->toArray(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const item = items[0];
                                const names = item?.dataset?.merchantNames;

                                if (Array.isArray(names) && names[item.dataIndex]) {
                                    return names[item.dataIndex];
                                }

                                return item?.label ?? '';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: false,
                            font: { size: 10 },
                        },
                    },
                },
            }
        JS);
    }
}
