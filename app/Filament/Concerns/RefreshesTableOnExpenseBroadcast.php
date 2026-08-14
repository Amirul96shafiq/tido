<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * Refresh a Filament table when an expense is created or its status changes.
 *
 * @see docs/realtime-broadcasting.md
 */
trait RefreshesTableOnExpenseBroadcast
{
    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            ...parent::getListeners(),
            ...$this->expenseBroadcastListeners(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function expenseBroadcastListeners(): array
    {
        return [
            'echo-private:household.expenses,.ExpenseUpdated' => 'refreshExpensesTable',
        ];
    }

    public function refreshExpensesTable(): void
    {
        $this->resetTable();
    }
}
