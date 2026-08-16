<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Pages;

use App\Filament\Concerns\AppendsResourceLabelToEditTitle;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Resources\Recurrings\Schemas\RecurringForm;
use App\Models\Recurring;
use App\Services\RecurringOccurrenceGenerator;
use App\Support\HouseholdAccess;
use App\Support\RecurringFormNormalizer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditRecurring extends EditRecord
{
    use AppendsResourceLabelToEditTitle;
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft {
        afterSave as protected clearContentDraftAfterSave;
    }

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
            Action::make('adjustNextDueOn')
                ->label('Adjust next due date')
                ->icon(Heroicon::CalendarDays)
                ->color('gray')
                ->modalWidth('sm')
                ->modalHeading('Adjust next due date')
                ->modalDescription('Updates the next due date only. Completed occurrence history is left unchanged.')
                ->form([
                    DatePicker::make('next_due_on')
                        ->label('Next due on')
                        ->native(false)
                        ->required()
                        ->default(fn (): ?string => $this->getRecord()->next_due_on?->toDateString()),
                ])
                ->authorize('update')
                ->action(function (array $data): void {
                    /** @var Recurring $record */
                    $record = $this->getRecord();
                    $record->next_due_on = $data['next_due_on'];
                    $record->save();

                    $generator = app(RecurringOccurrenceGenerator::class);
                    $generator->discardOpenOccurrencesBeforeNextDue($record->fresh());
                    $generator->generateFor($record->fresh());

                    Notification::make()
                        ->title('Next due date updated')
                        ->success()
                        ->send();

                    $this->refreshFormData(['next_due_on']);
                    $this->fillForm();
                }),

            RecurringResource::duplicateAction(),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Recurring $record */
        $record = $this->getRecord();

        return RecurringForm::hydrateFormData($data, $record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Recurring $record */
        $record = $this->getRecord();

        // Preserve persisted next_due_on unless the Adjust action changed it.
        unset($data['next_due_on']);

        if (HouseholdAccess::isFamilyMember()) {
            $data = RecurringFormNormalizer::preserveOwnership($data, $record);
        }

        return RecurringFormNormalizer::normalize($data, $record);
    }

    protected function afterSave(): void
    {
        $this->clearContentDraftAfterSave();
        app(RecurringOccurrenceGenerator::class)->generateFor($this->getRecord());
    }
}
