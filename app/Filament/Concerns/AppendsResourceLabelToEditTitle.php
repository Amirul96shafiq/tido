<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Appends the resource singular model label to Edit page titles and highlights
 * the record title in primary color.
 *
 * Example: Edit <primary>Overall Budget · Monthly 2026</primary> Budget
 */
trait AppendsResourceLabelToEditTitle
{
    public function getTitle(): string|Htmlable
    {
        $recordTitle = e((string) $this->getRecordTitle());
        $modelLabel = e(static::getResource()::getTitleCaseModelLabel());

        return new HtmlString(trim(
            __('filament-panels::resources/pages/edit-record.title', [
                'label' => '<span class="text-primary-600 dark:text-primary-400">'.$recordTitle.'</span>',
            ]).' '.$modelLabel
        ));
    }
}
