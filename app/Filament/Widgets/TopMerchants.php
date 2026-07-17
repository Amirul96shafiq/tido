<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasChartEmptyState;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class TopMerchants extends ChartWidget
{
    use HasChartEmptyState;
    use InteractsWithDashboardMonth;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.chart-with-empty-state';

    private const LABEL_LIMIT = 10;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 4,
    ];

    protected ?string $maxHeight = DashboardWidgetHeights::STANDARD_CHART;

    public function getHeading(): string|Htmlable|null
    {
        return 'Top Merchants ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'bar';
    }

    protected function isChartEmpty(): bool
    {
        return $this->analytics()->topMerchants()->isEmpty();
    }

    protected function getEmptyStateHeading(): string
    {
        return 'No merchants';
    }

    protected function getEmptyStateDescription(): string
    {
        return 'No merchant spending recorded for this month.';
    }

    protected function getEmptyStateIcon(): string
    {
        return 'heroicon-o-building-storefront';
    }

    protected function getData(): array
    {
        $merchants = $this->analytics()->topMerchants();

        if ($merchants->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $names = $merchants->pluck('merchant_name');

        return [
            'datasets' => [
                [
                    'label' => 'Total Spent (RM)',
                    'data' => $merchants->pluck('total_spent')->toArray(),
                    'backgroundColor' => '#FFD07D',
                    'merchantNames' => $names->toArray(),
                    'receiptCounts' => $merchants->pluck('receipt_count')->toArray(),
                    'avgSpends' => $merchants->pluck('avg_spend')->toArray(),
                    'spendSharePercents' => $merchants->pluck('spend_share_percent')->toArray(),
                ],
            ],
            'labels' => $merchants
                ->map(fn (object $merchant): string => Str::limit($merchant->merchant_name, self::LABEL_LIMIT)." ({$merchant->receipt_count})")
                ->toArray(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                maintainAspectRatio: false,
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
                            afterTitle: (items) => {
                                const item = items[0];
                                const index = item?.dataIndex;
                                const dataset = item?.dataset;

                                if (index === undefined || !dataset) {
                                    return '';
                                }

                                const share = dataset.spendSharePercents?.[index];
                                const receipts = dataset.receiptCounts?.[index];
                                const avgSpend = dataset.avgSpends?.[index];

                                const parts = [];

                                if (share !== undefined) {
                                    parts.push(`${share.toFixed(1)}% of month spend`);
                                }

                                if (receipts !== undefined) {
                                    parts.push(`${receipts} receipt${receipts === 1 ? '' : 's'}`);
                                }

                                if (avgSpend !== undefined) {
                                    parts.push(`RM ${avgSpend.toFixed(2)} avg/visit`);
                                }

                                return parts;
                            },
                            label: (item) => {
                                const value = item.parsed?.y ?? item.raw ?? 0;

                                return `${item.dataset.label}: RM ${Number(value).toFixed(2)}`;
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
