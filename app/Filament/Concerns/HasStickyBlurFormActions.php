<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\VerticalAlignment;

/**
 * Sticky bottom form CTAs with tido blur veil.
 *
 * @see docs/ui-sticky-blur.md
 */
trait HasStickyBlurFormActions
{
    use HasSectionNav;

    public function getFormContentComponent(): Component
    {
        $formActionComponents = [
            $this->getFormActionsContentComponent(),
        ];

        if (method_exists($this, 'saveDraft')) {
            $formActionComponents[] = View::make('filament.hooks.content-draft-poller');
        }

        return $this->wrapInSectionNavScope([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler($this->getStickyBlurFormLivewireSubmitHandler()),
            Group::make([
                Flex::make($formActionComponents)
                    ->alignBetween()
                    ->verticalAlignment(VerticalAlignment::Center)
                    ->extraAttributes([
                        'class' => 'tido-sticky-form-actions-row',
                    ]),
            ])->extraAttributes([
                'class' => 'tido-sticky-marker tido-sticky-marker--bottom',
            ]),
        ]);
    }

    public function getFormActionsContentComponent(): Component
    {
        /** @var array<Action|ActionGroup> $actions */
        $actions = array_map(
            function (Action|ActionGroup $action): Action|ActionGroup {
                if ($action instanceof Action && $action->canSubmitForm() && blank($action->getFormId())) {
                    return $action->formId('form');
                }

                return $action;
            },
            $this->getFormActions(),
        );

        return Actions::make($actions)
            ->alignment($this->getFormActionsAlignment())
            ->fullWidth($this->hasFullWidthFormActions())
            ->sticky(false)
            ->key('form-actions');
    }

    protected function getStickyBlurFormLivewireSubmitHandler(): string
    {
        if (method_exists($this, 'getSubmitFormLivewireMethodName')) {
            return $this->getSubmitFormLivewireMethodName();
        }

        return 'save';
    }
}
