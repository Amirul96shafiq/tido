<?php

declare(strict_types=1);

namespace App\Filament\Livewire;

use App\Enums\EvolutionApiConnectionEvent;
use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Models\EvolutionApiConnectionLog;
use App\Models\ServiceHealthSample;
use App\Services\EvolutionInstanceService;
use App\Support\HouseholdAccess;
use App\Support\PhoneNumber;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Livewire\Concerns\HasUserMenu;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class MobileNav extends Component implements HasActions, HasSchemas
{
    use HasUserMenu;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function render(): View
    {
        $whatsApp = $this->resolveWhatsAppChannel();

        return view('filament.livewire.mobile-nav', [
            'homeUrl' => Dashboard::getUrl(),
            'receiptUrl' => ReceiptUploadPage::getUrl(),
            'whatsAppUrl' => $whatsApp['url'],
            'isWhatsAppConnected' => $whatsApp['isConnected'],
            'whatsAppDisconnectedMessage' => $whatsApp['disconnectedMessage'],
            'budgetCreateUrl' => BudgetResource::getUrl('create'),
            'recurringCreateUrl' => RecurringResource::getUrl('create'),
            'labelCreateUrl' => LabelResource::getUrl('create'),
            'paymentMethodCreateUrl' => PaymentMethodResource::getUrl('create'),
            'familyMemberCreateUrl' => FamilyMemberResource::getUrl('create'),
            'canCreateFinances' => HouseholdAccess::isPrimary(),
            'canCreateSettings' => HouseholdAccess::isPrimary(),
            'createDeniedMessage' => HouseholdAccess::createDeniedMessage(),
        ]);
    }

    /**
     * @return array{isConnected: bool, url: ?string, disconnectedMessage: string}
     */
    protected function resolveWhatsAppChannel(): array
    {
        $disconnectedMessage = 'WhatsApp is not connected. Configure in Integrations → WhatsApp.';

        $connectedNumber = self::getConnectedWhatsAppNumber();

        if ($connectedNumber === null) {
            return [
                'isConnected' => false,
                'url' => null,
                'disconnectedMessage' => $disconnectedMessage,
            ];
        }

        $template = "Merchant Name, Payment Method;\nItem Name, 1, 1;";
        $url = 'https://api.whatsapp.com/send?'.http_build_query([
            'phone' => '+'.$connectedNumber,
            'text' => $template,
        ]);

        return [
            'isConnected' => true,
            'url' => $url,
            'disconnectedMessage' => $disconnectedMessage,
        ];
    }

    public static function getConnectedWhatsAppNumber(): ?string
    {
        if (! app(EvolutionInstanceService::class)->isConfigured()) {
            return null;
        }

        $latestLog = EvolutionApiConnectionLog::query()->latest('id')->first();

        if ($latestLog?->event === EvolutionApiConnectionEvent::Connected && filled($latestLog->connected_number)) {
            $sample = ServiceHealthSample::query()
                ->where('service', MonitoredService::Evolution->value)
                ->latest('checked_at')
                ->first();

            if ($sample === null || $sample->status === ServiceHealthStatus::Operational) {
                return PhoneNumber::normalize($latestLog->connected_number);
            }

            return null;
        }

        if ($latestLog !== null && $latestLog->event !== EvolutionApiConnectionEvent::Connected) {
            return null;
        }

        return Cache::remember('tido.mobile_nav_connected_whatsapp_number', 30, function (): ?string {
            try {
                $details = app(EvolutionInstanceService::class)->fetchInstanceDetails();

                if ($details['ok'] && in_array(strtolower((string) $details['connectionStatus']), ['open', 'connected'], true)) {
                    return PhoneNumber::normalize($details['connectedNumber']);
                }
            } catch (\Throwable) {
                // Ignore fallback exceptions
            }

            return null;
        });
    }
}
