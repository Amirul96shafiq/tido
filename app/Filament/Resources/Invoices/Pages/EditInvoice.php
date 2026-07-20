<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\ReceiptReparseService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class EditInvoice extends EditRecord
{
    use AppendsResourceLabelToEditTitle;
    use HasStickyBlurFormActions;
    use RecoversContentDraft;

    protected static string $resource = InvoiceResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-invoice-form-page',
        ];
    }

    protected function contentDraftKey(): string
    {
        return 'invoice-edit-'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reparse')
                ->label('Reparse')
                ->icon(Heroicon::ArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reparse receipt')
                ->modalDescription('Clear line items, reset status to pending, and queue OCR again.')
                ->visible(function (): bool {
                    /** @var Invoice $record */
                    $record = $this->getRecord();

                    return filled($record->image_path) && Storage::exists((string) $record->image_path);
                })
                ->action(function (ReceiptReparseService $reparseService): void {
                    /** @var Invoice $record */
                    $record = $this->getRecord();
                    $reparseService->reparse($record);

                    Notification::make()
                        ->title('Reparse queued')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'merchant_name',
                        'invoice_number',
                        'date_time',
                        'subtotal',
                        'total_tax',
                        'discount_total',
                        'rounding_amount',
                        'total_amount',
                        'payment_method_id',
                    ]);
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
