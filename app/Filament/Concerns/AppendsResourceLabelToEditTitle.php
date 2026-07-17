<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Appends the resource singular model label to Edit page titles.
 *
 * Example: "Edit Overall Budget · Monthly 2026" → "Edit Overall Budget · Monthly 2026 Budget"
 */
trait AppendsResourceLabelToEditTitle
{
    public function getTitle(): string|Htmlable
    {
        $title = parent::getTitle();
        $modelLabel = static::getResource()::getTitleCaseModelLabel();

        if (! is_string($title)) {
            $title = $title->toHtml();
        }

        return trim($title.' '.$modelLabel);
    }
}
