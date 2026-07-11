<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ReceiptManualReviewNotifier
{
    public function notify(Invoice $invoice): void
    {
        $merchant = filled($invoice->merchant_name)
            ? (string) $invoice->merchant_name
            : 'Unknown merchant';

        $filename = filled($invoice->original_filename)
            ? (string) $invoice->original_filename
            : null;

        $body = $filename !== null
            ? "\"{$filename}\" from {$merchant} could not be parsed automatically."
            : "A receipt from {$merchant} could not be parsed automatically.";

        $viewUrl = InvoiceResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $invoice->getRouteKey(),
        ]);
        $editUrl = InvoiceResource::getUrl('edit', ['record' => $invoice]);

        foreach (User::query()->cursor() as $user) {
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
                ->sendToDatabase($user);
        }
    }
}
