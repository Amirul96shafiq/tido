<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\InvoiceItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class SpendingByLabeling extends ChartWidget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Spending by Labeling ('.$this->formatSelectedMonth('F Y').')';
    }

    public function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $bounds = $this->getSelectedMonthBounds();

        $spending = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('labelings', 'invoice_items.labeling_id', '=', 'labelings.id')
            ->whereBetween('invoices.date_time', [$bounds['start'], $bounds['end']])
            ->whereIn('invoices.status', ['parsed', 'reviewed'])
            ->selectRaw('labelings.name, labelings.color, SUM(invoice_items.line_total) as total')
            ->groupBy('labelings.name', 'labelings.color')
            ->get();

        $labels = $spending->pluck('name')->toArray();
        $data = $spending->pluck('total')->map(fn ($val) => (float) $val)->toArray();
        $colors = $spending->pluck('color')->map(fn ($c) => $c ?: '#cccccc')->toArray();

        if (empty($data)) {
            $labels = ['No Expenses'];
            $data = [0];
            $colors = ['#e5e7eb'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Spent (RM)',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
