<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\MoneyDisplay;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\NotificationRecipient;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Cache;

class BudgetAlertService
{
    public function __construct(protected WhatsAppNotificationService $waService) {}

    public function checkAlertsForExpense(Expense $expense): void
    {
        $labelIds = $expense->expenseItems()->pluck('label_id')->unique()->filter()->toArray();

        $budgets = Budget::query()
            ->with('familyMember.loginUser')
            ->where('is_active', true)
            ->where(function ($query) use ($labelIds): void {
                $query->whereIn('label_id', $labelIds)
                    ->orWhereNull('label_id');
            })
            ->get();

        foreach ($budgets as $budget) {
            if (! $budget->appliesToExpense($expense)) {
                continue;
            }

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

        if ($budget->notify_whatsapp) {
            $this->sendWhatsAppAlerts($budget, $message);
        }

        if (! $budget->notify_filament) {
            return;
        }

        $filamentTitle = ($isCritical ? 'Budget Critical: ' : 'Budget Alert: ').$labelName;
        $filamentBody = MoneyDisplay::withPrefix($spent).' / '.MoneyDisplay::withPrefix($budgetAmount).' ('.round($percentage).'%)';

        $this->sendFilamentAlerts($budget, $filamentTitle, $filamentBody, $isCritical);
    }

    private function sendWhatsAppAlerts(Budget $budget, string $message): void
    {
        $numbers = [];

        $primaryNumber = PhoneNumber::primaryWhatsAppNumber();
        if ($primaryNumber !== null) {
            $numbers[$primaryNumber] = true;
        }

        $owner = $budget->familyMember;
        if ($owner instanceof FamilyMember && filled($owner->phone)) {
            $normalized = PhoneNumber::normalize((string) $owner->phone);
            if ($normalized !== null) {
                $numbers[$normalized] = true;
            }
        }

        foreach (array_keys($numbers) as $number) {
            $this->waService->sendMessage((string) $number, $message);
        }
    }

    private function sendFilamentAlerts(
        Budget $budget,
        string $title,
        string $body,
        bool $isCritical,
    ): void {
        $recipients = [];

        $primary = NotificationRecipient::primaryAdmin();
        if ($primary instanceof User && $primary->notify_budget_alerts) {
            $recipients[$primary->getKey()] = $primary;
        }

        $ownerUser = $budget->familyMember?->loginUser;
        if (
            $ownerUser instanceof User
            && $budget->familyMember?->login_enabled
            && $ownerUser->notify_budget_alerts
        ) {
            $recipients[$ownerUser->getKey()] = $ownerUser;
        }

        foreach ($recipients as $user) {
            $notification = FilamentNotification::make()
                ->title($title)
                ->body($body);

            if ($isCritical) {
                $notification->danger();
            } else {
                $notification->warning();
            }

            $notification->sendToDatabase($user);
        }
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
