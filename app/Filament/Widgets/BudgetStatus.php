<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Budget;
use Filament\Widgets\Widget;

class BudgetStatus extends Widget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 7,
    ];

    protected string $view = 'filament.widgets.budget-status';

    protected function getViewData(): array
    {
        $spentTotals = $this->analytics()->spentTotalsByLabelId();

        $budgets = Budget::with('label')
            ->where('is_active', true)
            ->get();

        $budgetStates = [];

        foreach ($budgets as $budget) {
            $labelKey = $budget->label_id ?? 0;
            $spent = $spentTotals[$labelKey] ?? 0.0;
            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
            $warnThreshold = (float) $budget->alert_threshold;
            $criticalThreshold = (float) $budget->critical_threshold;

            $budgetStates[] = [
                'name' => $budget->display_title,
                'icon' => $budget->display_icon,
                'amount' => (float) $budget->amount,
                'spent' => $spent,
                'percentage' => min(100, $percentage),
                'raw_percentage' => $percentage,
                'period' => ucfirst($budget->period),
                'color' => $budget->label ? $budget->label->color : '#FFD07D',
                'status_color' => $percentage >= $criticalThreshold
                    ? 'red'
                    : ($percentage >= $warnThreshold ? 'amber' : 'emerald'),
            ];
        }

        return [
            'budgets' => $budgetStates,
            'monthLabel' => $this->formatSelectedMonth('F Y'),
            'contentHeight' => DashboardWidgetHeights::STANDARD_CHART,
        ];
    }
}
