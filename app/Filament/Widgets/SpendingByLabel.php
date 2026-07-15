<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class SpendingByLabel extends ChartWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Spending by Label ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $spending = $this->analytics()->spentByLabel();

        if ($spending->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Spent (RM)',
                        'data' => [0],
                        'backgroundColor' => ['#e5e7eb'],
                        'receiptCounts' => [0],
                        'ranks' => [null],
                        'labelCount' => 0,
                        'momDeltas' => [null],
                        'momPercents' => [null],
                        'topMerchantNames' => [null],
                        'topMerchantTotals' => [null],
                        'priorMonthLabel' => $this->previousMonthLabel(),
                        'isEmpty' => true,
                    ],
                ],
                'labels' => ['No Expenses'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Spent (RM)',
                    'data' => $spending->pluck('total')->toArray(),
                    'backgroundColor' => $spending->pluck('color')->map(fn ($color) => $color ?: '#cccccc')->toArray(),
                    'receiptCounts' => $spending->pluck('receipt_count')->toArray(),
                    'ranks' => $spending->pluck('rank')->toArray(),
                    'labelCount' => $spending->first()->label_count,
                    'momDeltas' => $spending->map(fn (object $row): float => $row->mom_change['delta'])->toArray(),
                    'momPercents' => $spending->map(fn (object $row): ?float => $row->mom_change['percent'])->toArray(),
                    'topMerchantNames' => $spending->map(fn (object $row): ?string => $row->top_merchant['name'] ?? null)->toArray(),
                    'topMerchantTotals' => $spending->map(fn (object $row): ?float => $row->top_merchant['total'] ?? null)->toArray(),
                    'priorMonthLabel' => $this->previousMonthLabel(),
                    'isEmpty' => false,
                ],
            ],
            'labels' => $spending->pluck('name')->toArray(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (item) => {
                                const value = item.raw ?? 0;

                                return `Spent: RM ${Number(value).toFixed(2)}`;
                            },
                            afterTitle: (items) => {
                                const item = items[0];
                                const index = item?.dataIndex;
                                const dataset = item?.dataset;

                                if (index === undefined || !dataset || dataset.isEmpty) {
                                    return '';
                                }

                                const parts = [];
                                const rank = dataset.ranks?.[index];
                                const labelCount = dataset.labelCount;

                                if (rank !== undefined && rank !== null && labelCount) {
                                    parts.push(`#${rank} of ${labelCount} labels`);
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

                                return parts.join(' · ');
                            },
                            afterBody: (items) => {
                                const item = items[0];
                                const index = item?.dataIndex;
                                const dataset = item?.dataset;

                                if (index === undefined || !dataset || dataset.isEmpty) {
                                    return [];
                                }

                                const merchantName = dataset.topMerchantNames?.[index];
                                const merchantTotal = dataset.topMerchantTotals?.[index];

                                if (!merchantName || merchantTotal === undefined || merchantTotal === null) {
                                    return [];
                                }

                                return [`Top: ${merchantName} RM ${Number(merchantTotal).toFixed(2)}`];
                            },
                        },
                    },
                },
            }
        JS);
    }
}
