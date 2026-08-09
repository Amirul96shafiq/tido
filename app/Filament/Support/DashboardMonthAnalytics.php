<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\PaymentMethod;
use App\Support\DashboardSpenderScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DashboardMonthAnalytics
{
    /**
     * @var array<string, self>
     */
    private static array $instances = [];

    /**
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * @param  array{start: Carbon, end: Carbon, previous_start: Carbon, previous_end: Carbon}  $bounds
     */
    public function __construct(
        private readonly array $bounds,
        private readonly ?DashboardSpenderScope $spenderScope = null,
    ) {}

    /**
     * Request-scoped instance so dashboard widgets share aggregates.
     *
     * @param  array{start: Carbon, end: Carbon, previous_start: Carbon, previous_end: Carbon}  $bounds
     */
    public static function for(array $bounds, ?DashboardSpenderScope $spenderScope = null): self
    {
        $key = self::instanceKey($bounds, $spenderScope);

        return self::$instances[$key] ??= new self($bounds, $spenderScope);
    }

    public static function flushInstances(): void
    {
        self::$instances = [];
    }

    /**
     * @param  array{start: Carbon, end: Carbon, previous_start: Carbon, previous_end: Carbon}  $bounds
     */
    private static function instanceKey(array $bounds, ?DashboardSpenderScope $spenderScope): string
    {
        return implode('|', [
            $bounds['start']->format('c'),
            $bounds['end']->format('c'),
            $bounds['previous_start']->format('c'),
            $bounds['previous_end']->format('c'),
            $spenderScope?->value() ?? '',
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $key, callable $callback): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $callback();
        }

        return $this->memo[$key];
    }

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
        return $this->remember('summary', function (): array {
            $start = $this->bounds['start'];
            $end = $this->bounds['end'];
            $previousStart = $this->bounds['previous_start'];
            $previousEnd = $this->bounds['previous_end'];

            $pendingCount = $this->expenseQuery(canonicalMyr: false)
                ->whereBetween('date_time', [$start, $end])
                ->where('status', 'pending')
                ->count();

            $row = $this->expenseQuery()
                ->whereBetween('date_time', [$previousStart, $end])
                ->selectRaw(
                    'SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN total_amount ELSE 0 END) as current_total,
                    SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN total_amount ELSE 0 END) as previous_total,
                    SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN total_tax ELSE 0 END) as current_tax,
                    SUM(CASE WHEN date_time BETWEEN ? AND ? AND status IN (?, ?) THEN 1 ELSE 0 END) as processed_count',
                    [
                        $start, $end, 'parsed', 'reviewed',
                        $previousStart, $previousEnd, 'parsed', 'reviewed',
                        $start, $end, 'parsed', 'reviewed',
                        $start, $end, 'parsed', 'reviewed',
                    ],
                )
                ->first();

            return [
                'current_total' => (float) ($row->current_total ?? 0),
                'previous_total' => (float) ($row->previous_total ?? 0),
                'current_tax' => (float) ($row->current_tax ?? 0),
                'pending_count' => (int) $pendingCount,
                'processed_count' => (int) ($row->processed_count ?? 0),
            ];
        });
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     data: list<float>,
     *     tax_data: list<float>,
     *     selected_index: int,
     *     receipt_counts: list<int>,
     *     top_labels: list<list<array{name: string, total: float}>>,
     *     mom_changes: list<?array{delta: float, percent: ?float}>,
     *     period_shares: list<float>,
     * }
     */
    public function trend(int $months = 6, bool $calendarYear = false, bool $yearToDate = false): array
    {
        return $this->remember("trend:{$months}:".(int) $calendarYear.':'.(int) $yearToDate, function () use ($months, $calendarYear, $yearToDate): array {
            $endMonth = $this->bounds['start'];

            if ($yearToDate) {
                $months = $endMonth->month;
                $rangeStart = $endMonth->copy()->startOfYear();
                $rangeEnd = $endMonth->copy()->endOfMonth();
            } elseif ($calendarYear) {
                $months = 12;
                $rangeStart = $endMonth->copy()->startOfYear();
                $rangeEnd = $endMonth->copy()->endOfYear();
            } else {
                $rangeStart = $endMonth->copy()->subMonths($months - 1)->startOfMonth();
                $rangeEnd = $endMonth->copy()->endOfMonth();
            }

            $monthExpression = $this->monthTruncExpression('expenses.date_time');

            $monthlyStats = $this->expenseQuery()
                ->processed()
                ->whereBetween('date_time', [$rangeStart, $rangeEnd])
                ->selectRaw("{$monthExpression} as month_key, SUM(total_amount) as total, SUM(total_tax) as tax_total, COUNT(*) as receipt_count")
                ->groupBy('month_key')
                ->get()
                ->keyBy('month_key');

            $labelRows = $this->labelSpendingQuery($rangeStart, $rangeEnd)
                ->selectRaw("{$monthExpression} as month_key, labels.name, SUM(expense_items.line_total) as total")
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
            $taxData = [];
            $receiptCounts = [];
            $topLabels = [];

            for ($i = 0; $i < $months; $i++) {
                $month = ($calendarYear || $yearToDate)
                    ? $endMonth->copy()->startOfYear()->addMonths($i)
                    : $endMonth->copy()->subMonths($months - 1 - $i);
                $key = $month->format('Y-m');
                $labels[] = $month->format('m/y');
                $stats = $monthlyStats->get($key);
                $data[] = (float) ($stats->total ?? 0);
                $taxData[] = (float) ($stats->tax_total ?? 0);
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
                'tax_data' => $taxData,
                'selected_index' => ($calendarYear || $yearToDate) ? $endMonth->month - 1 : $months - 1,
                'receipt_counts' => $receiptCounts,
                'top_labels' => $topLabels,
                'mom_changes' => $momChanges,
                'period_shares' => $periodShares,
            ];
        });
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
        return $this->remember('spentByLabel', function (): Collection {
            $start = $this->bounds['start'];
            $end = $this->bounds['end'];
            $previousStart = $this->bounds['previous_start'];
            $previousEnd = $this->bounds['previous_end'];

            $rows = $this->labelSpendingQuery($start, $end)
                ->selectRaw('labels.id as label_id, labels.name, labels.color, SUM(expense_items.line_total) as total, COUNT(DISTINCT expenses.id) as receipt_count')
                ->groupBy('labels.id', 'labels.name', 'labels.color')
                ->orderByDesc('total')
                ->orderBy('labels.name')
                ->get();

            $priorTotals = $this->labelSpendingQuery($previousStart, $previousEnd)
                ->selectRaw('labels.id as label_id, SUM(expense_items.line_total) as total')
                ->groupBy('labels.id')
                ->pluck('total', 'label_id')
                ->map(fn ($total): float => (float) $total);

            $topMerchantsByLabel = [];

            foreach (
                $this->labelSpendingQuery($start, $end)
                    ->selectRaw('labels.id as label_id, expenses.merchant_name, SUM(expense_items.line_total) as total')
                    ->groupBy('labels.id', 'expenses.merchant_name')
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
    public function topMerchants(int $limit = 3): Collection
    {
        return $this->remember("topMerchants:{$limit}", function () use ($limit): Collection {
            $monthTotal = $this->summary()['current_total'];

            return $this->expenseQuery()
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
        });
    }

    /**
     * @return Collection<int, object{
     *     key: string,
     *     label: string,
     *     color: string,
     *     total: float,
     *     receipt_count: int,
     *     spend_share_percent: float,
     *     mom_change: array{delta: float, percent: ?float},
     * }>
     */
    public function spentByPaymentMethod(int $limit = 3): Collection
    {
        return $this->remember("spentByPaymentMethod:{$limit}", function () use ($limit): Collection {
            $start = $this->bounds['start'];
            $end = $this->bounds['end'];
            $previousStart = $this->bounds['previous_start'];
            $previousEnd = $this->bounds['previous_end'];
            $monthTotal = $this->summary()['current_total'];

            $rows = $this->expenseQuery()
                ->processed()
                ->inPeriod($start, $end)
                ->selectRaw('payment_method_id, SUM(total_amount) as total, COUNT(*) as receipt_count')
                ->groupBy('payment_method_id')
                ->orderByDesc('total')
                ->get();

            $methods = PaymentMethod::query()
                ->withTrashed()
                ->whereIn('id', $rows->pluck('payment_method_id')->filter()->all())
                ->get()
                ->keyBy('id');

            $priorTotals = $this->expenseQuery()
                ->processed()
                ->inPeriod($previousStart, $previousEnd)
                ->selectRaw('payment_method_id, SUM(total_amount) as total')
                ->groupBy('payment_method_id')
                ->get()
                ->keyBy(fn ($row): string => $this->paymentMethodKey($row->payment_method_id))
                ->map(fn ($row): float => (float) $row->total);

            return $rows
                ->map(function ($row) use ($priorTotals, $monthTotal, $methods): object {
                    $key = $this->paymentMethodKey($row->payment_method_id);
                    $paymentMethod = $row->payment_method_id !== null
                        ? ($methods->get((int) $row->payment_method_id) ?? null)
                        : null;
                    $total = (float) $row->total;
                    $priorTotal = (float) ($priorTotals[$key] ?? 0);
                    $delta = $total - $priorTotal;

                    return (object) [
                        'key' => $key,
                        'label' => $paymentMethod instanceof PaymentMethod ? $paymentMethod->name : 'Unknown',
                        'color' => $this->paymentMethodColor($paymentMethod),
                        'total' => $total,
                        'receipt_count' => (int) $row->receipt_count,
                        'spend_share_percent' => $monthTotal > 0 ? ($total / $monthTotal) * 100 : 0.0,
                        'mom_change' => [
                            'delta' => $delta,
                            'percent' => $priorTotal > 0 ? ($delta / $priorTotal) * 100 : null,
                        ],
                    ];
                })
                ->take($limit)
                ->values();
        });
    }

    /**
     * Fixed upload channels shown on the Receipts by Source chart (including zeros).
     *
     * @var list<string>
     */
    private const SOURCE_CHANNELS = [
        'whatsapp_parse',
        'whatsapp_manual',
        'google_drive',
        'manual',
    ];

    /**
     * @return Collection<int, object{
     *     key: string,
     *     label: string,
     *     color: string,
     *     receipt_count: int,
     *     total_spent: float,
     *     receipt_share_percent: float,
     *     mom_change: array{delta: float, percent: ?float},
     * }>
     */
    public function receiptsBySource(): Collection
    {
        return $this->remember('receiptsBySource', function (): Collection {
            $start = $this->bounds['start'];
            $end = $this->bounds['end'];
            $previousStart = $this->bounds['previous_start'];
            $previousEnd = $this->bounds['previous_end'];
            $channelExpression = $this->sourceChannelExpression();

            $rows = $this->expenseQuery()
                ->processed()
                ->inPeriod($start, $end)
                ->selectRaw("{$channelExpression} as source_channel, COUNT(*) as receipt_count, SUM(total_amount) as total_spent")
                ->groupByRaw($channelExpression)
                ->get()
                ->keyBy(fn ($row): string => $this->sourceKey($row->source_channel));

            if ($rows->isEmpty()) {
                return collect();
            }

            $priorCounts = $this->expenseQuery()
                ->processed()
                ->inPeriod($previousStart, $previousEnd)
                ->selectRaw("{$channelExpression} as source_channel, COUNT(*) as receipt_count")
                ->groupByRaw($channelExpression)
                ->get()
                ->keyBy(fn ($row): string => $this->sourceKey($row->source_channel))
                ->map(fn ($row): int => (int) $row->receipt_count);

            $monthReceiptTotal = (int) $rows->sum('receipt_count');

            return collect(self::SOURCE_CHANNELS)
                ->map(function (string $key) use ($rows, $priorCounts, $monthReceiptTotal): object {
                    $row = $rows->get($key);
                    $receiptCount = (int) ($row->receipt_count ?? 0);
                    $priorCount = (int) ($priorCounts[$key] ?? 0);
                    $delta = $receiptCount - $priorCount;

                    return (object) [
                        'key' => $key,
                        'label' => $this->sourceLabel($key),
                        'color' => $this->sourceColor($key),
                        'receipt_count' => $receiptCount,
                        'total_spent' => (float) ($row->total_spent ?? 0),
                        'receipt_share_percent' => $monthReceiptTotal > 0 ? ($receiptCount / $monthReceiptTotal) * 100 : 0.0,
                        'mom_change' => [
                            'delta' => (float) $delta,
                            'percent' => $priorCount > 0 ? ($delta / $priorCount) * 100 : null,
                        ],
                    ];
                })
                ->values();
        });
    }

    /**
     * @return array<int, float>
     */
    public function spentTotalsByLabelId(): array
    {
        return $this->remember('spentTotalsByLabelId', function (): array {
            $totals = [];

            foreach ($this->spentByLabel() as $row) {
                $totals[$row->label_id] = $row->total;
            }

            $overall = $this->labelSpendingQuery($this->bounds['start'], $this->bounds['end'])
                ->sum('expense_items.line_total');

            $totals[0] = (float) $overall;

            return $totals;
        });
    }

    private function paymentMethodKey(mixed $paymentMethodId): string
    {
        if (is_int($paymentMethodId) || (is_string($paymentMethodId) && ctype_digit($paymentMethodId))) {
            return (string) $paymentMethodId;
        }

        return '_unknown';
    }

    private function paymentMethodColor(?PaymentMethod $paymentMethod): string
    {
        return DashboardChartColors::forPaymentMethod($paymentMethod);
    }

    private function sourceChannelExpression(): string
    {
        return "CASE
            WHEN source = 'whatsapp' AND (image_path IS NULL OR image_path = '') THEN 'whatsapp_manual'
            WHEN source = 'whatsapp' THEN 'whatsapp_parse'
            ELSE source
        END";
    }

    private function sourceKey(mixed $source): string
    {
        if (is_string($source) && $source !== '') {
            return $source;
        }

        return '_unknown';
    }

    private function sourceLabel(mixed $source): string
    {
        return match ($source) {
            'manual' => 'Manual Upload',
            'whatsapp_parse' => 'WhatsApp (Parse)',
            'whatsapp_manual' => 'WhatsApp (Manual)',
            'whatsapp' => 'WhatsApp',
            'google_drive' => 'Google Drive',
            default => 'Unknown',
        };
    }

    private function sourceColor(mixed $source): string
    {
        return DashboardChartColors::forSource($source);
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
     * @return Builder<Expense>
     */
    private function expenseQuery(bool $canonicalMyr = true): Builder
    {
        $query = Expense::query();

        if ($this->spenderScope instanceof DashboardSpenderScope) {
            $query = $this->spenderScope->applyToExpenseQuery($query);
        }

        if ($canonicalMyr) {
            $query->canonicalMyr();
        }

        return $query;
    }

    /**
     * @return Builder<ExpenseItem>
     */
    private function labelSpendingQuery(Carbon $start, Carbon $end): Builder
    {
        $query = ExpenseItem::query()
            ->join('expenses', 'expense_items.expense_id', '=', 'expenses.id')
            ->join('labels', 'expense_items.label_id', '=', 'labels.id')
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.date_time', [$start, $end])
            ->whereIn('expenses.status', Expense::dashboardAnalyticsStatuses())
            ->where('expenses.currency', Expense::CURRENCY_MYR)
            ->whereIn('expenses.currency_conversion_status', Expense::CANONICAL_CONVERSION_STATUSES);

        if ($this->spenderScope instanceof DashboardSpenderScope) {
            $query = $this->spenderScope->applyToExpensesJoin($query);
        }

        return $query;
    }
}
