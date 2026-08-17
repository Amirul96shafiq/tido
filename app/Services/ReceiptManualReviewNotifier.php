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

        $body = $filename !== null
            ? "\"{$filename}\" from {$merchant} could not be parsed automatically."
            : "A receipt from {$merchant} could not be parsed automatically.";

        $viewUrl = ExpenseResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $expense->getRouteKey(),
        ]);
        $editUrl = ExpenseResource::getUrl('edit', ['record' => $expense]);

        Notification::make()
            ->title('Receipt requires manual review')
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
