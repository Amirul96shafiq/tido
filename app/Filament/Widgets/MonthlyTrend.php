<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class MonthlyTrend extends ChartWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = DashboardWidgetHeights::TREND_CHART;

    public function getHeading(): string|Htmlable|null
    {
        return 'Monthly Spending Trend (12 months to '.$this->formatSelectedMonth('M Y').')';
    }

    public function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = $this->analytics()->trend(12);
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
                    'receiptCounts' => $trend['receipt_counts'],
                    'topLabelNames' => array_map(
                        fn (array $labels): array => array_column($labels, 'name'),
                        $trend['top_labels'],
                    ),
                    'topLabelTotals' => array_map(
                        fn (array $labels): array => array_column($labels, 'total'),
                        $trend['top_labels'],
                    ),
                    'momDeltas' => array_map(
                        fn (?array $change): ?float => $change !== null ? $change['delta'] : null,
                        $trend['mom_changes'],
                    ),
                    'momPercents' => array_map(
                        fn (?array $change): ?float => $change !== null ? $change['percent'] : null,
                        $trend['mom_changes'],
                    ),
                    'periodShares' => $trend['period_shares'],
                ],
            ],
            'labels' => $trend['labels'],
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
                            label: (item) => {
                                const value = item.parsed?.y ?? item.raw ?? 0;

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

                                if (index > 0) {
                                    const delta = dataset.momDeltas?.[index];
                                    const percent = dataset.momPercents?.[index];
                                    const priorLabel = item.chart?.data?.labels?.[index - 1];

                                    if (delta !== undefined && delta !== null) {
                                        const sign = delta >= 0 ? '+' : '-';
                                        let momText = `${sign}RM ${Math.abs(delta).toFixed(2)}`;

                                        if (percent !== undefined && percent !== null) {
                                            momText += ` (${sign}${Math.abs(percent).toFixed(1)}% vs ${priorLabel ?? 'prior month'})`;
                                        } else if (priorLabel) {
                                            momText += ` vs ${priorLabel}`;
                                        }

                                        parts.push(momText);
                                    }
                                }

                                const receipts = dataset.receiptCounts?.[index];

                                if (receipts !== undefined) {
                                    parts.push(`${receipts} receipt${receipts === 1 ? '' : 's'}`);
                                }

                                const share = dataset.periodShares?.[index];

                                if (share !== undefined) {
                                    parts.push(`${share.toFixed(1)}% of 12-mo total`);
                                }

                                return parts.join(' · ');
                            },
                            afterBody: (items) => {
                                const item = items[0];
                                const index = item?.dataIndex;
                                const dataset = item?.dataset;

                                if (index === undefined || !dataset) {
                                    return [];
                                }

                                const names = dataset.topLabelNames?.[index];
                                const totals = dataset.topLabelTotals?.[index];

                                if (!Array.isArray(names) || names.length === 0) {
                                    return ['No labeled spending'];
                                }

                                return names.map((name, labelIndex) => {
                                    const total = totals?.[labelIndex] ?? 0;

                                    return `${name} RM ${Number(total).toFixed(2)}`;
                                });
                            },
                        },
                    },
                },
            }
        JS);
    }
}
