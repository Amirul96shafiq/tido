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
    public function notify(Expense $invoice): void
    {
        $recipient = NotificationRecipient::forInvoice($invoice);

        if ($recipient === null) {
            return;
        }

        $merchant = filled($invoice->merchant_name)
            ? (string) $invoice->merchant_name
            : 'Unknown merchant';

        $filename = filled($invoice->original_filename)
            ? (string) $invoice->original_filename
            : null;

        $body = $filename !== null
            ? "\"{$filename}\" from {$merchant} could not be parsed automatically."
            : "A receipt from {$merchant} could not be parsed automatically.";

        $viewUrl = ExpenseResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $invoice->getRouteKey(),
        ]);
        $editUrl = ExpenseResource::getUrl('edit', ['record' => $invoice]);

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
