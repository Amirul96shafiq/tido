<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Support\NotificationRecipient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ReceiptManualReviewNotifier
{
    public function notify(Expense $expense): void
    {
        $recipient = NotificationRecipient::forExpense($expense);

        if ($recipient === null || ! $recipient->notify_receipt_review) {
            return;
        }

        $merchant = filled($expense->merchant_name)
            ? (string) $expense->merchant_name
            : 'Unknown merchant';

        $filename = filled($expense->original_filename)
            ? (string) $expense->original_filename
            : null;

        $isNotReceipt = $expense->isNotReceipt();
        $title = $isNotReceipt
            ? 'Non-receipt document requires manual review'
            : 'Receipt requires manual review';
        $body = $isNotReceipt
            ? ($filename !== null
                ? "\"{$filename}\" does not appear to contain receipt information. It was saved for manual review and excluded from spending analytics. Enter the expense details manually if needed."
                : 'This upload does not appear to contain receipt information. It was saved for manual review and excluded from spending analytics. Enter the expense details manually if needed.')
            : ($filename !== null
                ? "\"{$filename}\" from {$merchant} could not be parsed automatically."
                : "A receipt from {$merchant} could not be parsed automatically.");

        $viewUrl = ExpenseResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $expense->getRouteKey(),
        ]);
        $editUrl = ExpenseResource::getUrl('edit', ['record' => $expense]);

        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->button()
                    ->url($viewUrl, shouldOpenInNewTab: true)
                    ->markAsRead(),
                Action::make('edit')
                    ->label('Edit')
                    ->button()
                    ->color('gray')
                    ->url($editUrl, shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipient);
    }
}
