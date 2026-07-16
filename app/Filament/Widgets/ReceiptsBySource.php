<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasChartEmptyState;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class ReceiptsBySource extends ChartWidget
{
    use HasChartEmptyState;
    use InteractsWithDashboardMonth;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.chart-with-empty-state';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 4,
    ];

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = DashboardWidgetHeights::STANDARD_CHART;

    public function getHeading(): string|Htmlable|null
    {
        return 'Receipts by Upload Source ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'bar';
    }

    protected function isChartEmpty(): bool
    {
        return $this->analytics()->receiptsBySource()->isEmpty();
    }

    protected function getEmptyStateHeading(): string
    {
        return 'No receipts';
    }

    protected function getEmptyStateDescription(): string
    {
        return 'No receipts recorded for this month.';
    }

    protected function getEmptyStateIcon(): string
    {
        return 'heroicon-o-arrow-up-tray';
    }

    protected function getData(): array
    {
        $sources = $this->analytics()->receiptsBySource();

        if ($sources->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Receipts',
                    'data' => $sources->pluck('receipt_count')->toArray(),
                    'backgroundColor' => $sources->pluck('color')->toArray(),
                    'sourceLabels' => $sources->pluck('label')->toArray(),
                    'totalSpent' => $sources->pluck('total_spent')->toArray(),
                    'receiptSharePercents' => $sources->pluck('receipt_share_percent')->toArray(),
                    'momDeltas' => $sources->map(fn (object $row): float => $row->mom_change['delta'])->toArray(),
                    'momPercents' => $sources->map(fn (object $row): ?float => $row->mom_change['percent'])->toArray(),
                    'priorMonthLabel' => $this->previousMonthLabel(),
                ],
            ],
            'labels' => $sources->pluck('label')->toArray(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const item = items[0];
                                const labels = item?.dataset?.sourceLabels;

                                if (Array.isArray(labels) && labels[item.dataIndex]) {
                                    return labels[item.dataIndex];
                                }

                                return item?.label ?? '';
                            },
                            label: (item) => {
                                const value = item.parsed?.x ?? item.raw ?? 0;
                                const count = Number(value);

                                return `${count} receipt${count === 1 ? '' : 's'}`;
                            },
                            afterTitle: (items) => {
                                const item = items[0];
                                const index = item?.dataIndex;
                                const dataset = item?.dataset;

                                if (index === undefined || !dataset) {
                                    return '';
                                }

                                const parts = [];
                                const spent = dataset.totalSpent?.[index];

                                if (spent !== undefined && spent !== null) {
                                    parts.push(`RM ${Number(spent).toFixed(2)} spent`);
                                }

                                const share = dataset.receiptSharePercents?.[index];

                                if (share !== undefined) {
                                    parts.push(`${share.toFixed(1)}% of month receipts`);
                                }

                                const delta = dataset.momDeltas?.[index];
                                const percent = dataset.momPercents?.[index];
                                const priorMonthLabel = dataset.priorMonthLabel;

                                if (delta !== undefined && delta !== null) {
                                    const sign = delta >= 0 ? '+' : '-';
                                    const absDelta = Math.abs(delta);
                                    let momText = `${sign}${absDelta} receipt${absDelta === 1 ? '' : 's'}`;

                                    if (percent !== undefined && percent !== null) {
                                        momText += ` (${sign}${Math.abs(percent).toFixed(1)}% vs ${priorMonthLabel ?? 'prior month'})`;
                                    } else if (priorMonthLabel) {
                                        momText += ` vs ${priorMonthLabel}`;
                                    }

                                    parts.push(momText);
                                }

                                return parts.join(' · ');
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            precision: 0,
                        },
                    },
                    y: {
                        ticks: {
                            font: { size: 11 },
                        },
                    },
                },
            }
        JS);
    }
}
