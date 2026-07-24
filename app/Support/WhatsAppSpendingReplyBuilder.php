<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\Support\DashboardMonthAnalytics;
use App\Filament\Support\DashboardMonthPeriod;
use App\Helpers\MoneyDisplay;
use App\Models\Budget;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class WhatsAppSpendingReplyBuilder
{
    public function __construct(
        private readonly string $month,
        private readonly string $mode = WhatsAppSpendingCommandParser::MODE_SUMMARY,
    ) {}

    public function build(): string
    {
        return match ($this->mode) {
            WhatsAppSpendingCommandParser::MODE_LABELS => $this->buildLabels(),
            WhatsAppSpendingCommandParser::MODE_MERCHANTS => $this->buildMerchants(),
            WhatsAppSpendingCommandParser::MODE_BUDGETS => $this->buildBudgets(),
            WhatsAppSpendingCommandParser::MODE_TREND => $this->buildTrend(),
            WhatsAppSpendingCommandParser::MODE_PAYMENT => $this->buildPayment(),
            WhatsAppSpendingCommandParser::MODE_RECENT => $this->buildRecent(),
            default => $this->buildSummary(),
        };
    }

    private function analytics(): DashboardMonthAnalytics
    {
        return new DashboardMonthAnalytics(
            DashboardMonthPeriod::boundsFromFilters(['month' => $this->month]),
        );
    }

    private function bounds(): array
    {
        return DashboardMonthPeriod::boundsFromFilters(['month' => $this->month]);
    }

    private function monthLabel(): string
    {
        return DashboardMonthPeriod::labelFromFilters(['month' => $this->month]);
    }

    private function previousMonthLabel(): string
    {
        return DashboardMonthPeriod::previousMonthLabelFromFilters(['month' => $this->month]);
    }

    private function isCurrentMonth(): bool
    {
        return DashboardMonthPeriod::isCurrentMonth(
            Carbon::createFromFormat('Y-m', $this->month)->startOfMonth(),
        );
    }

    private function buildSummary(): string
    {
        $analytics = $this->analytics();
        $summary = $analytics->summary();
        $monthLabel = $this->monthLabel();
        $currentTotal = $summary['current_total'];
        $previousTotal = $summary['previous_total'];
        $lines = [
            "Period: *{$monthLabel}*",
            '',
            'Total spent: *'.MoneyDisplay::withPrefix($currentTotal).'*',
        ];

        if ($summary['processed_count'] === 0 && $summary['pending_count'] === 0) {
            $lines[] = '';
            $lines[] = "No receipts recorded for *{$monthLabel}*.";
        } else {
            $difference = $currentTotal - $previousTotal;
            $comparison = MoneyDisplay::withPrefix(abs($difference));

            if ($previousTotal > 0) {
                $percent = ($difference / $previousTotal) * 100;
                $comparison .= sprintf(' (%s%.1f%%)', $difference >= 0 ? '+' : '-', abs($percent));
            }

            $sign = $difference >= 0 ? '+' : '-';
            $lines[] = "{$sign}{$comparison} vs {$this->previousMonthLabel()}";
            $lines[] = '';
            $lines[] = sprintf(
                'Receipts: *%d* processed · *%d* pending',
                $summary['processed_count'],
                $summary['pending_count'],
            );

            $lines = array_merge($lines, $this->forecastLines($currentTotal));
            $lines = array_merge($lines, $this->topLabelLines($analytics->spentByLabel(), 3));
            $lines = array_merge($lines, $this->topMerchantLines($analytics->topMerchants(), 3));
            $lines = array_merge($lines, $this->budgetRiskLines($analytics));
        }

        return WhatsAppMessage::compose('💰', 'Monthly Spending', implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    private function forecastLines(float $currentTotal): array
    {
        if ($this->isCurrentMonth()) {
            $now = now();
            $currentDay = max(1, $now->day);
            $totalDays = $now->daysInMonth;
            $averageDailySpend = $currentTotal / $currentDay;
            $remainingDays = max(0, $totalDays - $currentDay);
            $projectedSpend = $currentTotal + ($averageDailySpend * $remainingDays);

            $lines = [
                '',
                'Forecast (end of month): *'.MoneyDisplay::withPrefix($projectedSpend).'*',
            ];

            $overallMonthlyBudget = Budget::query()
                ->whereNull('label_id')
                ->where('period', 'monthly')
                ->where('is_active', true)
                ->value('amount');

            $overallMonthlyBudget = $overallMonthlyBudget ? (float) $overallMonthlyBudget : null;

            if ($overallMonthlyBudget !== null && $overallMonthlyBudget > 0) {
                $budgetStatus = ($projectedSpend / $overallMonthlyBudget) * 100;

                if ($budgetStatus > 100) {
                    $displayBudgetPercent = (int) max(101, (int) round($budgetStatus));
                    $lines[] = sprintf('Projected to EXCEED budget (%d%%)', $displayBudgetPercent);
                } else {
                    $displayBudgetPercent = (int) round($budgetStatus);
                    $lines[] = sprintf(
                        'Projected at %d%% of budget (%s)',
                        $displayBudgetPercent,
                        MoneyDisplay::withPrefix($overallMonthlyBudget),
                    );
                }
            } else {
                $lines[] = 'Based on '.MoneyDisplay::withPrefix($averageDailySpend).' avg daily spend';
            }

            return $lines;
        }

        $daysInMonth = $this->bounds()['start']->daysInMonth;
        $dailyAverage = $daysInMonth > 0 ? $currentTotal / $daysInMonth : 0.0;

        return [
            '',
            'Daily average: *'.MoneyDisplay::withPrefix($dailyAverage).'*',
            sprintf('Across %d days in month', $daysInMonth),
        ];
    }

    private function buildLabels(): string
    {
        $labels = $this->analytics()->spentByLabel();

        if ($labels->isEmpty()) {
            return WhatsAppMessage::compose(
                '🏷️',
                'Spending by Label',
                "No label spending recorded for *{$this->monthLabel()}*.",
            );
        }

        $lines = [
            "Period: *{$this->monthLabel()}*",
            '',
        ];

        foreach ($labels->take(8) as $row) {
            $lines[] = sprintf(
                '• *%s* — %s (%d receipt%s)',
                $row->name,
                MoneyDisplay::withPrefix($row->total),
                $row->receipt_count,
                $row->receipt_count === 1 ? '' : 's',
            );
        }

        if ($labels->count() > 8) {
            $remaining = $labels->count() - 8;
            $lines[] = sprintf('…and %d more label%s.', $remaining, $remaining === 1 ? '' : 's');
        }

        return WhatsAppMessage::compose('🏷️', 'Spending by Label', implode("\n", $lines));
    }

    private function buildMerchants(): string
    {
        $merchants = $this->analytics()->topMerchants(5);

        if ($merchants->isEmpty()) {
            return WhatsAppMessage::compose(
                '🏪',
                'Top Merchants',
                "No merchant spending recorded for *{$this->monthLabel()}*.",
            );
        }

        $lines = [
            "Period: *{$this->monthLabel()}*",
            '',
        ];

        foreach ($merchants as $merchant) {
            $lines[] = sprintf(
                '• *%s* — %s (%d receipt%s, %.1f%% of month)',
                $merchant->merchant_name,
                MoneyDisplay::withPrefix($merchant->total_spent),
                $merchant->receipt_count,
                $merchant->receipt_count === 1 ? '' : 's',
                $merchant->spend_share_percent,
            );
        }

        return WhatsAppMessage::compose('🏪', 'Top Merchants', implode("\n", $lines));
    }

    private function buildBudgets(): string
    {
        $analytics = $this->analytics();
        $spentTotals = $analytics->spentTotalsByLabelId();

        $budgets = Budget::query()
            ->with('label')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($budgets->isEmpty()) {
            return WhatsAppMessage::compose(
                '📊',
                'Budget Status',
                "No active budgets configured for *{$this->monthLabel()}*.",
            );
        }

        $lines = [
            "Period: *{$this->monthLabel()}*",
            '',
        ];

        foreach ($budgets as $budget) {
            $labelKey = $budget->label_id ?? 0;
            $spent = $spentTotals[$labelKey] ?? 0.0;
            $amount = (float) $budget->amount;
            $percentage = $amount > 0 ? ($spent / $amount) * 100 : 0.0;
            $icon = $percentage >= (float) $budget->critical_threshold
                ? '🚨'
                : ($percentage >= (float) $budget->alert_threshold ? '⚠️' : '✅');

            $lines[] = sprintf(
                '%s *%s* — %.0f%% (%s / %s)',
                $icon,
                $budget->display_title,
                min(100, $percentage),
                MoneyDisplay::withPrefix($spent),
                MoneyDisplay::withPrefix($amount),
            );
        }

        return WhatsAppMessage::compose('📊', 'Budget Status', implode("\n", $lines));
    }

    private function buildTrend(): string
    {
        $trend = $this->analytics()->trend(6);
        $chunks = [];

        foreach ($trend['labels'] as $index => $label) {
            $total = $trend['data'][$index] ?? 0.0;
            $chunks[] = sprintf('%s %s', $label, MoneyDisplay::withPrefix($total));
        }

        $body = implode("\n", [
            'Last 6 months ending *'.$this->monthLabel().'*',
            '',
            implode("\n", array_map(static fn (string $chunk): string => '• '.$chunk, $chunks)),
        ]);

        return WhatsAppMessage::compose('📈', 'Spending Trend', $body);
    }

    private function buildPayment(): string
    {
        $methods = $this->analytics()->spentByPaymentMethod(5);

        if ($methods->isEmpty()) {
            return WhatsAppMessage::compose(
                '💳',
                'Spending by Payment Method',
                "No payment method spending recorded for *{$this->monthLabel()}*.",
            );
        }

        $lines = [
            "Period: *{$this->monthLabel()}*",
            '',
        ];

        foreach ($methods as $method) {
            $lines[] = sprintf(
                '• *%s* — %s (%d receipt%s, %.1f%% of month)',
                $method->label,
                MoneyDisplay::withPrefix($method->total),
                $method->receipt_count,
                $method->receipt_count === 1 ? '' : 's',
                $method->spend_share_percent,
            );
        }

        return WhatsAppMessage::compose('💳', 'Spending by Payment Method', implode("\n", $lines));
    }

    private function buildRecent(): string
    {
        $bounds = $this->bounds();

        $invoices = Invoice::query()
            ->with('paymentMethod')
            ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($invoices->isEmpty()) {
            return WhatsAppMessage::compose(
                '🧾',
                'Recent Receipts',
                "No receipts uploaded during *{$this->monthLabel()}*.",
            );
        }

        $lines = [
            "Period: *{$this->monthLabel()}*",
            '',
        ];

        foreach ($invoices as $invoice) {
            $lines[] = sprintf(
                '• *%s* — %s · %s',
                $invoice->merchant_name,
                MoneyDisplay::withPrefix($invoice->total_amount),
                $invoice->created_at?->format('j M') ?? '—',
            );
        }

        return WhatsAppMessage::compose('🧾', 'Recent Receipts', implode("\n", $lines));
    }

    /**
     * @param  Collection<int, object{name: string, total: float, receipt_count: int}>  $labels
     * @return list<string>
     */
    private function topLabelLines(Collection $labels, int $limit): array
    {
        if ($labels->isEmpty()) {
            return [];
        }

        $lines = ['', 'Top labels:'];

        foreach ($labels->take($limit) as $row) {
            $lines[] = sprintf(
                '• *%s* — %s',
                $row->name,
                MoneyDisplay::withPrefix($row->total),
            );
        }

        return $lines;
    }

    /**
     * @param  Collection<int, object{merchant_name: string, total_spent: float, receipt_count: int}>  $merchants
     * @return list<string>
     */
    private function topMerchantLines(Collection $merchants, int $limit): array
    {
        if ($merchants->isEmpty()) {
            return [];
        }

        $lines = ['', 'Top merchants:'];

        foreach ($merchants->take($limit) as $merchant) {
            $lines[] = sprintf(
                '• *%s* — %s (%d receipt%s)',
                $merchant->merchant_name,
                MoneyDisplay::withPrefix($merchant->total_spent),
                $merchant->receipt_count,
                $merchant->receipt_count === 1 ? '' : 's',
            );
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function budgetRiskLines(DashboardMonthAnalytics $analytics): array
    {
        $spentTotals = $analytics->spentTotalsByLabelId();

        $atRisk = Budget::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (Budget $budget) use ($spentTotals): bool {
                $labelKey = $budget->label_id ?? 0;
                $spent = $spentTotals[$labelKey] ?? 0.0;
                $amount = (float) $budget->amount;

                if ($amount <= 0) {
                    return false;
                }

                $percentage = ($spent / $amount) * 100;

                return $percentage >= (float) $budget->alert_threshold;
            });

        if ($atRisk->isEmpty()) {
            return [];
        }

        $lines = ['', 'Budgets at risk:'];

        foreach ($atRisk->take(3) as $budget) {
            $labelKey = $budget->label_id ?? 0;
            $spent = $spentTotals[$labelKey] ?? 0.0;
            $amount = (float) $budget->amount;
            $percentage = ($spent / $amount) * 100;
            $icon = $percentage >= (float) $budget->critical_threshold ? '🚨' : '⚠️';

            $lines[] = sprintf(
                '%s *%s* — %.0f%% (%s / %s)',
                $icon,
                $budget->display_title,
                min(100, $percentage),
                MoneyDisplay::withPrefix($spent),
                MoneyDisplay::withPrefix($amount),
            );
        }

        if ($atRisk->count() > 3) {
            $remaining = $atRisk->count() - 3;
            $lines[] = sprintf('…and %d more. Type *spend budgets* for full list.', $remaining);
        }

        return $lines;
    }
}
