<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    use PrependsHomeBreadcrumb;

    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
