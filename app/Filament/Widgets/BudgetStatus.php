<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Budget;
use App\Models\InvoiceItem;
use Filament\Widgets\Widget;

class BudgetStatus extends Widget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;
    protected string $view = 'filament.widgets.budget-status';

    protected function getViewData(): array
    {
        $budgets = Budget::with('category')
            ->where('is_active', true)
            ->get();

        $budgetStates = [];

        foreach ($budgets as $budget) {
            $start = $budget->getStartDate();
            $end = $budget->getEndDate();

            $query = InvoiceItem::query()
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->whereBetween('invoices.date_time', [$start, $end])
                ->whereIn('invoices.status', ['parsed', 'reviewed']);

            if ($budget->category_id) {
                $query->where('invoice_items.category_id', $budget->category_id);
            }

            $spent = (float) $query->sum('invoice_items.line_total');
            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;

            $budgetStates[] = [
                'name' => $budget->category ? $budget->category->name : 'Overall Budget',
                'amount' => (float) $budget->amount,
                'spent' => $spent,
                'percentage' => min(100, $percentage),
                'raw_percentage' => $percentage,
                'period' => ucfirst($budget->period),
                'color' => $budget->category ? $budget->category->color : '#1a73e8',
                'status_color' => $percentage >= 100 ? 'red' : ($percentage >= $budget->alert_threshold ? 'amber' : 'emerald'),
            ];
        }

        return [
            'budgets' => $budgetStates,
        ];
    }
}
