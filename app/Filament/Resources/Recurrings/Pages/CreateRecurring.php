<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Resources\Recurrings\Schemas\RecurringForm;
use App\Services\RecurringOccurrenceGenerator;
use App\Support\RecurringFormNormalizer;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurring extends CreateRecord
{
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft;

    protected static string $resource = RecurringResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-recurring-form-page',
        ];
    }

    protected function contentDraftKey(): string
    {
        return 'recurring-create';
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return RecurringForm::sectionNavItems();
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Recurring sections';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RecurringFormNormalizer::normalize($data);
    }

    protected function afterCreate(): void
    {
        app(RecurringOccurrenceGenerator::class)->generateFor($this->getRecord());
    }
}
