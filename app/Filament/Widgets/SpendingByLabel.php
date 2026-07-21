<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\HasChartEmptyState;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class SpendingByLabel extends ChartWidget
{
    use HasChartEmptyState;
    use InteractsWithDashboardMonth;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.chart-with-empty-state';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 5,
    ];

    protected ?string $maxHeight = DashboardWidgetHeights::STANDARD_CHART;

    public function getHeading(): string|Htmlable|null
    {
        return 'Spending by Label ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'doughnut';
    }

    protected function isChartEmpty(): bool
    {
        return $this->analytics()->spentByLabel()->isEmpty();
    }

    public function getEmptyStateHeading(): string
    {
        return 'No expenses';
    }

    public function getEmptyStateDescription(): string
    {
        return 'No label spending recorded for this month.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-tag';
    }

    protected function getData(): array
    {
        $spending = $this->analytics()->spentByLabel();

        if ($spending->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
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
                maintainAspectRatio: false,
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

                                return parts;
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
