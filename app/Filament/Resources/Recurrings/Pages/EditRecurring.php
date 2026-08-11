<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Pages;

use App\Enums\RecurringFrequency;
use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Resources\Recurrings\Schemas\RecurringForm;
use App\Services\RecurringOccurrenceGenerator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurring extends EditRecord
{
    use AppendsResourceLabelToEditTitle;
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
        return 'recurring-edit-'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $preset = $data['cadence_preset'] ?? null;

        if ($preset === 'once') {
            $data['frequency'] = RecurringFrequency::Once->value;
            $data['interval_months'] = null;
        } elseif (! isset($data['frequency'])) {
            $data['frequency'] = RecurringFrequency::Repeating->value;
        }

        if (($data['frequency'] ?? null) === RecurringFrequency::Repeating->value
            && empty($data['interval_months'])) {
            $data['interval_months'] = match ($preset) {
                'quarterly' => 3,
                'semiannual' => 6,
                'yearly' => 12,
                default => 1,
            };
        }

        unset($data['cadence_preset']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(RecurringOccurrenceGenerator::class)->generateFor($this->getRecord());
    }
}
