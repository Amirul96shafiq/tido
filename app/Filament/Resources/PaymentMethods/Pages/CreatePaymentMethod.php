<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\PaymentMethods\Schemas\PaymentMethodForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePaymentMethod extends CreateRecord
{
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
        return 'payment-method-create';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_system'] = false;
        $data['aliases'] = self::normalizeAliases($data['aliases'] ?? []);

        return $data;
    }

    /**
     * @return list<string>
     */
    public static function normalizeAliases(mixed $aliases): array
    {
        if (! is_array($aliases)) {
            return [];
        }

        $normalized = [];

        foreach ($aliases as $alias) {
            if (! is_string($alias) || blank($alias)) {
                continue;
            }

            $token = Str::lower(trim($alias));
            $token = str_replace([' ', '-', "'"], ['_', '_', ''], $token);

            if ($token === '') {
                continue;
            }

            $normalized[] = $token;
        }

        return array_values(array_unique($normalized));
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
