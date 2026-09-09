<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Filament\Support\ChartWidgetHeadingMarquee;
use Illuminate\Contracts\Support\Htmlable;

trait HasChartWidgetHeadingMarquee
{
    abstract protected function chartWidgetHeadingLabel(): string;

    public function usesHeadingMarquee(): bool
    {
        return true;
    }

    public function getHeading(): string|Htmlable|null
    {
        return ChartWidgetHeadingMarquee::html(
            $this->chartWidgetHeadingLabel().' ('.$this->formatSelectedMonth('F Y').')',
        );
    }
}
