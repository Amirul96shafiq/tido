<?php

declare(strict_types=1);

namespace App\Filament\Livewire;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Support\HouseholdAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Livewire\Concerns\HasUserMenu;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MobileNav extends Component implements HasActions, HasSchemas
{
    use HasUserMenu;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function render(): View
    {
        return view('filament.livewire.mobile-nav', [
            'homeUrl' => Dashboard::getUrl(),
            'receiptUrl' => ReceiptUploadPage::getUrl(),
            'budgetCreateUrl' => BudgetResource::getUrl('create'),
            'recurringCreateUrl' => RecurringResource::getUrl('create'),
            'canCreateFinances' => HouseholdAccess::isPrimary(),
            'createDeniedMessage' => HouseholdAccess::createDeniedMessage(),
        ]);
    }
}
