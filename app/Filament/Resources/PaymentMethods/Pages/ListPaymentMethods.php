<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    use PrependsHomeBreadcrumb;

    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
