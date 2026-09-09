<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Looping marquee for narrow Filament chart widget section headings.
 *
 * @see docs/ui-text-marquee.md
 */
final class ChartWidgetHeadingMarquee
{
    public static function html(string $text): Htmlable
    {
        return new HtmlString(
            (string) view('filament.support.chart-widget-heading-marquee', [
                'text' => $text,
            ]),
        );
    }
}
