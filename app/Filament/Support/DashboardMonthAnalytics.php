<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DashboardMonthAnalytics
{
    /**
     * @param  array{start: Carbon, end: Carbon, previous_start: Carbon, previous_end: Carbon}  $bounds
     */
    public function __construct(
        private readonly array $bounds,
    ) {}

    /**
     * @return array{
     *     current_total: float,
     *     previous_total: float,
     *     current_tax: float,
     *     pending_count: int,
     *     processed_count: int,
     * }
     */
    public function summary(): array
    {
        $start = $this->bounds['start'];
        $end = $this->bounds['end'];
        $previousStart = $this->bounds['previous_start'];
        $previousEnd = $this->bounds['previous_end'];

        $row = Invoice::query()
            ->whereBetween('date_time', [$previousStart, $end])
            ->selectRaw(
                'SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN total_amount ELSE 0 END) as current_total,
                SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN total_amount ELSE 0 END) as previous_total,
                SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN total_tax ELSE 0 END) as current_tax,
                SUM(CASE WHEN date_time BETWEEN ? AND ? AND status = ? THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN 1 ELSE 0 END) as processed_count',
                [
                    $start, $end, 'parsed', 'reviewed',
                    $previousStart, $previousEnd, 'parsed', 'reviewed',
                    $start, $end, 'parsed', 'reviewed',
                    $start, $end, 'pending',
                    $start, $end, 'parsed', 'reviewed',
                ],
            )
            ->first();

        return [
            'current_total' => (float) ($row->current_total ?? 0),
            'previous_total' => (float) ($row->previous_total ?? 0),
            'current_tax' => (float) ($row->current_tax ?? 0),
            'pending_count' => (int) ($row->pending_count ?? 0),
            'processed_count' => (int) ($row->processed_count ?? 0),
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     data: list<float>,
     *     selected_index: int,
     *     receipt_counts: list<int>,
     *     top_labels: list<list<array{name: string, total: float}>>,
     *     mom_changes: list<?array{delta: float, percent: ?float}>,
     *     period_shares: list<float>,
     * }
     */
    public function trend(int $months = 6): array
    {
        $endMonth = $this->bounds['start'];
        $rangeStart = $endMonth->copy()->subMonths($months - 1)->startOfMonth();
        $rangeEnd = $endMonth->copy()->endOfMonth();

        $monthExpression = $this->monthTruncExpression('invoices.date_time');

        $monthlyStats = Invoice::query()
            ->processed()
            ->whereBetween('date_time', [$rangeStart, $rangeEnd])
            ->selectRaw("{$monthExpression} as month_key, SUM(total_amount) as total, COUNT(*) as receipt_count")
            ->groupBy('month_key')
            ->get()
            ->keyBy('month_key');

        $labelRows = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('labels', 'invoice_items.label_id', '=', 'labels.id')
            ->whereBetween('invoices.date_time', [$rangeStart, $rangeEnd])
            ->whereIn('invoices.status', Invoice::dashboardAnalyticsStatuses())
            ->selectRaw("{$monthExpression} as month_key, labels.name, SUM(invoice_items.line_total) as total")
            ->groupBy('month_key', 'labels.name', 'labels.id')
            ->get();

        $topLabelsByMonthKey = [];

        foreach ($labelRows->groupBy('month_key') as $monthKey => $rows) {
            $topLabelsByMonthKey[(string) $monthKey] = $rows
                ->sortByDesc('total')
                ->take(3)
                ->map(fn ($row): array => [
                    'name' => (string) $row->name,
                    'total' => (float) $row->total,
                ])
                ->values()
                ->all();
        }

        $labels = [];
        $data = [];
        $receiptCounts = [];
        $topLabels = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $endMonth->copy()->subMonths($i);
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');
            $stats = $monthlyStats->get($key);
            $data[] = (float) ($stats->total ?? 0);
            $receiptCounts[] = (int) ($stats->receipt_count ?? 0);
            $topLabels[] = $topLabelsByMonthKey[$key] ?? [];
        }

        $periodTotal = array_sum($data);
        $periodShares = array_map(
            fn (float $total): float => $periodTotal > 0 ? ($total / $periodTotal) * 100 : 0.0,
            $data,
        );

        $momChanges = [];

        foreach ($data as $index => $total) {
            if ($index === 0) {
                $momChanges[] = null;

                continue;
            }

            $previous = $data[$index - 1];
            $delta = $total - $previous;

            $momChanges[] = [
                'delta' => $delta,
                'percent' => $previous > 0 ? ($delta / $previous) * 100 : null,
            ];
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'selected_index' => $months - 1,
            'receipt_counts' => $receiptCounts,
            'top_labels' => $topLabels,
            'mom_changes' => $momChanges,
            'period_shares' => $periodShares,
        ];
    }

    /**
     * @return Collection<int, object{
     *     label_id: int,
     *     name: string,
     *     color: string|null,
     *     total: float,
     *     receipt_count: int,
     *     rank: int,
     *     label_count: int,
     *     mom_change: array{delta: float, percent: ?float},
     *     top_merchant: ?array{name: string, total: float},
     * }>
     */
    public function spentByLabel(): Collection
    {
        $start = $this->bounds['start'];
        $end = $this->bounds['end'];
        $previousStart = $this->bounds['previous_start'];
        $previousEnd = $this->bounds['previous_end'];

        $rows = $this->labelSpendingQuery($start, $end)
            ->selectRaw('labels.id as label_id, labels.name, labels.color, SUM(invoice_items.line_total) as total, COUNT(DISTINCT invoices.id) as receipt_count')
            ->groupBy('labels.id', 'labels.name', 'labels.color')
            ->orderByDesc('total')
            ->orderBy('labels.name')
            ->get();

        $priorTotals = $this->labelSpendingQuery($previousStart, $previousEnd)
            ->selectRaw('labels.id as label_id, SUM(invoice_items.line_total) as total')
            ->groupBy('labels.id')
            ->pluck('total', 'label_id')
            ->map(fn ($total): float => (float) $total);

        $topMerchantsByLabel = [];

        foreach (
            $this->labelSpendingQuery($start, $end)
                ->selectRaw('labels.id as label_id, invoices.merchant_name, SUM(invoice_items.line_total) as total')
                ->groupBy('labels.id', 'invoices.merchant_name')
                ->get()
                ->groupBy('label_id') as $labelId => $merchants
        ) {
            $topMerchant = $merchants->sortByDesc('total')->first();

            if ($topMerchant === null) {
                continue;
            }

            $topMerchantsByLabel[(int) $labelId] = [
                'name' => (string) $topMerchant->merchant_name,
                'total' => (float) $topMerchant->total,
            ];
        }

        $labelCount = $rows->count();

        return $rows->values()->map(function ($row, int $index) use ($priorTotals, $topMerchantsByLabel, $labelCount): object {
            $labelId = (int) $row->label_id;
            $total = (float) $row->total;
            $priorTotal = (float) ($priorTotals[$labelId] ?? 0);
            $delta = $total - $priorTotal;

            return (object) [
                'label_id' => $labelId,
                'name' => (string) $row->name,
                'color' => $row->color,
                'total' => $total,
                'receipt_count' => (int) $row->receipt_count,
                'rank' => $index + 1,
                'label_count' => $labelCount,
                'mom_change' => [
                    'delta' => $delta,
                    'percent' => $priorTotal > 0 ? ($delta / $priorTotal) * 100 : null,
                ],
                'top_merchant' => $topMerchantsByLabel[$labelId] ?? null,
            ];
        });
    }

    /**
     * @return Collection<int, object{
     *     merchant_name: string,
     *     total_spent: float,
     *     total_discount: float,
     *     receipt_count: int,
     *     avg_spend: float,
     *     spend_share_percent: float,
     * }>
     */
    public function topMerchants(int $limit = 5): Collection
    {
        $monthTotal = $this->summary()['current_total'];

        return Invoice::query()
            ->processed()
            ->inPeriod($this->bounds['start'], $this->bounds['end'])
            ->selectRaw('
                merchant_name,
                SUM(total_amount) as total_spent,
                SUM(discount_total) as total_discount,
                COUNT(*) as receipt_count
            ')
            ->groupBy('merchant_name')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($monthTotal): object {
                $totalSpent = (float) $row->total_spent;
                $receiptCount = (int) $row->receipt_count;

                return (object) [
                    'merchant_name' => (string) $row->merchant_name,
                    'total_spent' => $totalSpent,
                    'total_discount' => (float) $row->total_discount,
                    'receipt_count' => $receiptCount,
                    'avg_spend' => $receiptCount > 0 ? $totalSpent / $receiptCount : 0.0,
                    'spend_share_percent' => $monthTotal > 0 ? ($totalSpent / $monthTotal) * 100 : 0.0,
                ];
            });
    }

    /**
     * @return array<int, float>
     */
    public function spentTotalsByLabelId(): array
    {
        $totals = [];

        foreach ($this->spentByLabel() as $row) {
            $totals[$row->label_id] = $row->total;
        }

        $overall = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereBetween('invoices.date_time', [$this->bounds['start'], $this->bounds['end']])
            ->whereIn('invoices.status', Invoice::dashboardAnalyticsStatuses())
            ->sum('invoice_items.line_total');

        $totals[0] = (float) $overall;

        return $totals;
    }

    private function monthTruncExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(DATE_TRUNC('month', {$column}), 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * @return Builder<InvoiceItem>
     */
    private function labelSpendingQuery(Carbon $start, Carbon $end): Builder
    {
        return InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('labels', 'invoice_items.label_id', '=', 'labels.id')
            ->whereBetween('invoices.date_time', [$start, $end])
            ->whereIn('invoices.status', Invoice::dashboardAnalyticsStatuses());
    }
}
