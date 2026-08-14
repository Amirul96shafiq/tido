<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ExpenseUpdated;
use App\Models\Expense;
use App\Models\ExpenseItem;

class ExpenseItemObserver
{
    /**
     * @var list<string>
     */
    private const SYNC_ATTRIBUTES = [
        'label_id',
        'line_total',
        'quantity',
        'unit_price',
        'expense_id',
    ];

    public function created(ExpenseItem $expenseItem): void
    {
        $this->broadcastParent($expenseItem);
    }

    public function updated(ExpenseItem $expenseItem): void
    {
        if (! $expenseItem->wasChanged(self::SYNC_ATTRIBUTES)) {
            return;
        }

        $this->broadcastParent($expenseItem);
    }

    public function deleted(ExpenseItem $expenseItem): void
    {
        $this->broadcastParent($expenseItem);
    }

    private function broadcastParent(ExpenseItem $expenseItem): void
    {
        $expense = $expenseItem->expense;

        if (! $expense instanceof Expense) {
            return;
        }

        ExpenseUpdated::dispatch($expense->id, (string) $expense->status);
    }
}
