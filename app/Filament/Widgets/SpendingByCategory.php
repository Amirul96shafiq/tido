<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\InvoiceItem;
use Filament\Widgets\ChartWidget;

class SpendingByCategory extends ChartWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected ?string $heading = 'Spending by Category (This Month)';
    
    public function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $now = now();
        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();

        $spending = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('categories', 'invoice_items.category_id', '=', 'categories.id')
            ->whereBetween('invoices.date_time', [$thisMonthStart, $thisMonthEnd])
            ->whereIn('invoices.status', ['parsed', 'reviewed'])
            ->selectRaw('categories.name, categories.color, SUM(invoice_items.line_total) as total')
            ->groupBy('categories.name', 'categories.color')
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
