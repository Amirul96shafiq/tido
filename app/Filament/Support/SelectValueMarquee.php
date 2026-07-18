<?php

declare(strict_types=1);

namespace App\Filament\Support;

/**
 * Shared opt-in class for Filament JS select selected-value marquee.
 *
 * @see docs/ui-text-marquee.md
 */
final class SelectValueMarquee
{
    public const EXTRA_CLASS = 'tido-select-value-marquee';

    /**
     * @return array{class: string}
     */
    public static function extraAttributes(): array
    {
        return ['class' => self::EXTRA_CLASS];
    }
}
