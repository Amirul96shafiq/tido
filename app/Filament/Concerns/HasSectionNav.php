<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;

/**
 * Sticky in-page section tab navigation.
 *
 * @see docs/ui-section-nav.md
 */
trait HasSectionNav
{
    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [];
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Page sections';
    }

    protected function hasSectionNav(): bool
    {
        return static::sectionNavItems() !== [];
    }

    protected function getSectionNavTopMarkerComponent(): Component
    {
        return Group::make([
            View::make('filament.schemas.components.section-nav')
                ->viewData(fn (): array => [
                    'sections' => static::sectionNavItems(),
                    'ariaLabel' => $this->sectionNavAriaLabel(),
                ]),
        ])->extraAttributes([
            'class' => 'tido-sticky-marker tido-sticky-marker--top',
        ]);
    }

    /**
     * @param  list<Component>  $components
     */
    protected function wrapInSectionNavScope(array $components): Component
    {
        $children = [];

        if ($this->hasSectionNav()) {
            $children[] = $this->getSectionNavTopMarkerComponent();
        }

        return Group::make([
            ...$children,
            ...$components,
        ])->extraAttributes([
            'class' => 'tido-sticky-scope',
        ]);
    }
}
