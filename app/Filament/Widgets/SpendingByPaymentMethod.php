<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasChartEmptyState;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class SpendingByPaymentMethod extends ChartWidget
{
    use HasChartEmptyState;
    use InteractsWithDashboardMonth;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.chart-with-empty-state';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 4,
    ];

    protected ?string $maxHeight = DashboardWidgetHeights::STANDARD_CHART;

    public function getHeading(): string|Htmlable|null
    {
        return 'Spending by Payment Method ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'bar';
    }

    protected function isChartEmpty(): bool
    {
        return $this->analytics()->spentByPaymentMethod()->isEmpty();
    }

    public function getEmptyStateHeading(): string
    {
        return 'No expenses';
    }

    public function getEmptyStateDescription(): string
    {
        return 'No payment method spending recorded for this month.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-credit-card';
    }

    protected function getData(): array
    {
        $methods = $this->analytics()->spentByPaymentMethod();

        if ($methods->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Spent (RM)',
                    'data' => $methods->pluck('total')->toArray(),
                    'backgroundColor' => $methods->pluck('color')->toArray(),
                    'methodLabels' => $methods->pluck('label')->toArray(),
                    'receiptCounts' => $methods->pluck('receipt_count')->toArray(),
                    'spendSharePercents' => $methods->pluck('spend_share_percent')->toArray(),
                    'momDeltas' => $methods->map(fn (object $row): float => $row->mom_change['delta'])->toArray(),
                    'momPercents' => $methods->map(fn (object $row): ?float => $row->mom_change['percent'])->toArray(),
                    'priorMonthLabel' => $this->previousMonthLabel(),
                ],
            ],
            'labels' => $methods
                ->map(fn (object $method): string => "{$method->label} ({$method->receipt_count})")
                ->toArray(),
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
                                const labels = item?.dataset?.methodLabels;

                                if (Array.isArray(labels) && labels[item.dataIndex]) {
                                    return labels[item.dataIndex];
                                }

                                return item?.label ?? '';
                            },
                            label: (item) => {
                                const value = item.parsed?.x ?? item.raw ?? 0;

                                return `Spent: RM ${Number(value).toFixed(2)}`;
                            },
                            afterTitle: (items) => {
                                const item = items[0];
                                const index = item?.dataIndex;
                                const dataset = item?.dataset;

                                if (index === undefined || !dataset) {
                                    return '';
                                }

                                const parts = [];
                                const share = dataset.spendSharePercents?.[index];

                                if (share !== undefined) {
                                    parts.push(`${share.toFixed(1)}% of month spend`);
                                }

                                const receipts = dataset.receiptCounts?.[index];

                                if (receipts !== undefined) {
                                    parts.push(`${receipts} receipt${receipts === 1 ? '' : 's'}`);
                                }

                                const delta = dataset.momDeltas?.[index];
                                const percent = dataset.momPercents?.[index];
                                const priorMonthLabel = dataset.priorMonthLabel;

                                if (delta !== undefined && delta !== null) {
                                    const sign = delta >= 0 ? '+' : '-';
                                    let momText = `${sign}RM ${Math.abs(delta).toFixed(2)}`;

                                    if (percent !== undefined && percent !== null) {
                                        momText += ` (${sign}${Math.abs(percent).toFixed(1)}% vs ${priorMonthLabel ?? 'prior month'})`;
                                    } else if (priorMonthLabel) {
                                        momText += ` vs ${priorMonthLabel}`;
                                    }

                                    parts.push(momText);
                                }

                                return parts;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            callback: (value) => `RM ${value}`,
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
