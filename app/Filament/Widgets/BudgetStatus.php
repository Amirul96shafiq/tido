<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Support\DashboardWidgetHeights;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Models\Budget;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class BudgetStatus extends Widget
{
    use CanPoll;
    use InteractsWithDashboardMonth;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '5s';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 7,
    ];

    protected string $view = 'filament.widgets.budget-status';

    public function reorderBudgets(int|string $id, int $position): void
    {
        $budgetId = (int) $id;

        $orderedIds = Budget::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        $fromIndex = array_search($budgetId, $orderedIds, true);

        if ($fromIndex === false) {
            return;
        }

        $position = max(0, min($position, count($orderedIds) - 1));

        array_splice($orderedIds, $fromIndex, 1);
        array_splice($orderedIds, $position, 0, [$budgetId]);

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $orderedId) {
                Budget::query()
                    ->whereKey($orderedId)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    protected function getViewData(): array
    {
        $spentTotals = $this->analytics()->spentTotalsByLabelId();

        $budgets = Budget::with('label')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $budgetStates = [];

        foreach ($budgets as $budget) {
            $labelKey = $budget->label_id ?? 0;
            $spent = $spentTotals[$labelKey] ?? 0.0;
            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
            $warnThreshold = (float) $budget->alert_threshold;
            $criticalThreshold = (float) $budget->critical_threshold;

            $budgetStates[] = [
                'id' => $budget->id,
                'edit_url' => BudgetResource::getUrl('edit', ['record' => $budget]),
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
