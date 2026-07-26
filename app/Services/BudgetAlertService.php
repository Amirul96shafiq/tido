<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\MoneyDisplay;
use App\Models\Budget;
use App\Models\Invoice;
use App\Support\NotificationRecipient;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Cache;

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
        if (! $this->claimAlertSlot($budget, $level)) {
            return;
        }

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

            $personalNumber = PhoneNumber::primaryWhatsAppNumber();
            if ($personalNumber !== null) {
                $this->waService->sendMessage($personalNumber, $message);
            }
        }

        if (! $budget->notify_filament) {
            return;
        }

        $user = NotificationRecipient::primaryAdmin();

        if ($user === null || ! $user->notify_budget_alerts) {
            return;
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

    /**
     * @param  'warn'|'critical'  $level
     */
    private function claimAlertSlot(Budget $budget, string $level): bool
    {
        $periodStart = $budget->getStartDate()->toDateString();
        $cacheKey = sprintf('budget-alert:%d:%s:%s', $budget->id, $level, $periodStart);
        $ttlSeconds = max(60, (int) now()->diffInSeconds($budget->getEndDate(), false));

        return Cache::add($cacheKey, true, $ttlSeconds);
    }
}
