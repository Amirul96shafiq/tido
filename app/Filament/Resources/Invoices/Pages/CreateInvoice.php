<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    use HasStickyBlurFormActions;
    use RecoversContentDraft;

    protected static string $resource = InvoiceResource::class;

    protected function contentDraftKey(): string
    {
        return 'invoice-create';
    }
}
