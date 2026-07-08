<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Budget;
use Filament\Widgets\Widget;

class BudgetStatus extends Widget
{
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.budget-status';

    protected function getViewData(): array
    {
        $spentTotals = $this->analytics()->spentTotalsByLabelingId();

        $budgets = Budget::with('labeling')
            ->where('is_active', true)
            ->get();

        $budgetStates = [];

        foreach ($budgets as $budget) {
            $labelingKey = $budget->labeling_id ?? 0;
            $spent = $spentTotals[$labelingKey] ?? 0.0;
            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;

            $budgetStates[] = [
                'name' => $budget->labeling ? $budget->labeling->name : 'Overall Budget',
                'amount' => (float) $budget->amount,
                'spent' => $spent,
                'percentage' => min(100, $percentage),
                'raw_percentage' => $percentage,
                'period' => ucfirst($budget->period),
                'color' => $budget->labeling ? $budget->labeling->color : '#FFD07D',
                'status_color' => $percentage >= 100 ? 'red' : ($percentage >= $budget->alert_threshold ? 'amber' : 'emerald'),
            ];
        }

        return [
            'budgets' => $budgetStates,
            'monthLabel' => $this->formatSelectedMonth('F Y'),
        ];
    }
}
