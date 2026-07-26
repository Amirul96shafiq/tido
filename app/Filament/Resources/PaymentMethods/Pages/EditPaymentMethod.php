<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\PaymentMethods\Schemas\PaymentMethodForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethod extends EditRecord
{
    use AppendsResourceLabelToEditTitle;
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft;

    protected static string $resource = PaymentMethodResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-payment-method-form-page',
        ];
    }

    protected function contentDraftKey(): string
    {
        return 'payment-method-edit-'.$this->getRecord()->getKey();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['aliases'] = CreatePaymentMethod::normalizeAliases($data['aliases'] ?? []);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! (bool) $this->record->is_system),
            RestoreAction::make(),
        ];
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return PaymentMethodForm::sectionNavItems();
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Payment method sections';
    }
}
