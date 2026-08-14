<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * Refresh a Filament widget when an expense is created or its status changes.
 *
 * Chart widgets should override {@see refreshFromExpenseBroadcast()} to call
 * `updateChartData()` because the chart canvas is `wire:ignore`.
 *
 * @see docs/realtime-broadcasting.md
 */
trait RefreshesOnExpenseBroadcast
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
            'echo-private:household.expenses,.ExpenseUpdated' => 'refreshOnExpenseBroadcast',
        ];
    }

    public function refreshOnExpenseBroadcast(): void
    {
        if (method_exists($this, 'isCurrentMonthSelected') && ! $this->isCurrentMonthSelected()) {
            $this->skipRender();

            return;
        }

        $this->refreshFromExpenseBroadcast();
    }

    protected function refreshFromExpenseBroadcast(): void
    {
        // Livewire round-trip re-renders stats and custom Blade widgets.
    }
}
