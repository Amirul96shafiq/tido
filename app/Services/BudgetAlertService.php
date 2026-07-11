<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Support\WhatsAppMessage;
use Filament\Notifications\Notification as FilamentNotification;

class BudgetAlertService
{
    public function __construct(protected WhatsAppNotificationService $waService) {}

    public function checkAlertsForInvoice(Invoice $invoice): void
    {
        $labelIds = $invoice->invoiceItems()->pluck('label_id')->unique()->filter()->toArray();

        $budgets = Budget::whereIn('label_id', $labelIds)
            ->orWhereNull('label_id')
            ->where('is_active', true)
            ->get();

        foreach ($budgets as $budget) {
            $start = $budget->getStartDate();
            $end = $budget->getEndDate();

            $query = InvoiceItem::query()
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->whereBetween('invoices.date_time', [$start, $end])
                ->whereIn('invoices.status', ['parsed', 'reviewed']);

            if ($budget->label_id) {
                $query->where('invoice_items.label_id', $budget->label_id);
            }

            $spent = (float) $query->sum('invoice_items.line_total');
            $budgetAmount = (float) $budget->amount;

            if ($budgetAmount <= 0) {
                continue;
            }

            $percentage = ($spent / $budgetAmount) * 100;
            $threshold = (float) $budget->alert_threshold;

            if ($percentage >= $threshold) {
                $labelName = $budget->label ? (string) $budget->label->getAttribute('name') : 'Overall Budget';
                $periodName = ucfirst($budget->period);

                $message = WhatsAppMessage::compose(
                    '⚠️',
                    'Budget alert',
                    sprintf(
                        "Spending for this label has reached the alert threshold.\n\nLabel: *%s*\nSpent: *RM %s* / *RM %s* (%.1f%%)\nPeriod: *%s*",
                        $labelName,
                        number_format($spent, 2),
                        number_format($budgetAmount, 2),
                        $percentage,
                        $periodName,
                    ),
                );

                $personalNumber = config('services.evolution.personal_number');
                if (! empty($personalNumber)) {
                    $this->waService->sendMessage((string) $personalNumber, $message);
                }

                $users = User::all();

                foreach ($users as $user) {
                    if (! $user->notify_budget_alerts) {
                        continue;
                    }

                    FilamentNotification::make()
                        ->title("Budget Alert: {$labelName}")
                        ->body('Spent: RM '.number_format($spent, 2).' / RM '.number_format($budgetAmount, 2).' ('.round($percentage).'%)')
                        ->warning()
                        ->sendToDatabase($user);
                }
            }
        }
    }
}
