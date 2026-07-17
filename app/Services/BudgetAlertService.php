<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\MoneyDisplay;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\User;
use App\Support\WhatsAppMessage;
use Filament\Notifications\Notification as FilamentNotification;

class BudgetAlertService
{
    public function __construct(protected WhatsAppNotificationService $waService) {}

    public function checkAlertsForInvoice(Invoice $invoice): void
    {
        $labelIds = $invoice->invoiceItems()->pluck('label_id')->unique()->filter()->toArray();

        $budgets = Budget::query()
            ->where('is_active', true)
            ->where(function ($query) use ($labelIds): void {
                $query->whereIn('label_id', $labelIds)
                    ->orWhereNull('label_id');
            })
            ->get();

        foreach ($budgets as $budget) {
            $budgetAmount = (float) $budget->amount;

            if ($budgetAmount <= 0) {
                continue;
            }

            $spent = $budget->spentInPeriod();
            $percentage = ($spent / $budgetAmount) * 100;
            $warnThreshold = (float) $budget->alert_threshold;
            $criticalThreshold = (float) $budget->critical_threshold;

            $level = match (true) {
                $percentage >= $criticalThreshold => 'critical',
                $percentage >= $warnThreshold => 'warn',
                default => null,
            };

            if ($level === null) {
                continue;
            }

            $this->dispatchAlert($budget, $spent, $budgetAmount, $percentage, $level);
        }
    }

    /**
     * @param  'warn'|'critical'  $level
     */
    private function dispatchAlert(
        Budget $budget,
        float $spent,
        float $budgetAmount,
        float $percentage,
        string $level,
    ): void {
        $labelName = $budget->display_title;
        $periodName = ucfirst((string) $budget->period);
        $isCritical = $level === 'critical';
        $alertHeading = $isCritical ? 'Budget critical' : 'Budget alert';

        if ($budget->notify_whatsapp) {
            $message = WhatsAppMessage::compose(
                $isCritical ? '🚨' : '⚠️',
                $alertHeading,
                sprintf(
                    "Spending for this budget has reached the %s threshold.\n\nBudget: *%s*\nSpent: *RM %s* / *RM %s* (%.1f%%)\nPeriod: *%s*",
                    $isCritical ? 'critical' : 'warning',
                    $labelName,
                    MoneyDisplay::format($spent),
                    MoneyDisplay::format($budgetAmount),
                    $percentage,
                    $periodName,
                ),
            );

            $personalNumber = config('services.evolution.personal_number');
            if (! empty($personalNumber)) {
                $this->waService->sendMessage((string) $personalNumber, $message);
            }
        }

        if (! $budget->notify_filament) {
            return;
        }

        $users = User::all();

        foreach ($users as $user) {
            if (! $user->notify_budget_alerts) {
                continue;
            }

            $notification = FilamentNotification::make()
                ->title(($isCritical ? 'Budget Critical: ' : 'Budget Alert: ').$labelName)
                ->body(MoneyDisplay::withPrefix($spent).' / '.MoneyDisplay::withPrefix($budgetAmount).' ('.round($percentage).'%)');

            if ($isCritical) {
                $notification->danger();
            } else {
                $notification->warning();
            }

            $notification->sendToDatabase($user);
        }
    }
}
