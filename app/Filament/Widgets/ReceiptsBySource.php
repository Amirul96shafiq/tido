<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class ReceiptsBySource extends ChartWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public function getHeading(): string|Htmlable|null
    {
        return 'Receipts by Upload Source ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getDescription(): ?string
    {
        if ($this->analytics()->receiptsBySource()->isEmpty()) {
            return 'No receipts recorded for this month.';
        }

        return null;
    }

    public function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $sources = $this->analytics()->receiptsBySource();

        if ($sources->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Receipts',
                        'data' => [],
                        'backgroundColor' => [],
                        'sourceLabels' => [],
                        'totalSpent' => [],
                        'receiptSharePercents' => [],
                        'momDeltas' => [],
                        'momPercents' => [],
                        'priorMonthLabel' => $this->previousMonthLabel(),
                    ],
                ],
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
