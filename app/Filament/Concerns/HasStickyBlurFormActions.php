<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;

/**
 * Sticky bottom form CTAs with tido blur veil.
 *
 * @see docs/ui-sticky-blur.md
 */
trait HasStickyBlurFormActions
{
    public function getFormContentComponent(): Component
    {
        return Group::make([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler($this->getStickyBlurFormLivewireSubmitHandler()),
            Group::make([
                $this->getFormActionsContentComponent(),
            ])->extraAttributes([
                'class' => 'tido-sticky-marker tido-sticky-marker--bottom',
            ]),
        ])->extraAttributes([
            'class' => 'tido-sticky-scope',
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
