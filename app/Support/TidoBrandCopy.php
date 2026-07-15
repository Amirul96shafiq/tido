<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

final class TidoBrandCopy
{
    public static function loginHeading(): string
    {
        return 'Keep it tidy. Get it done.';
    }

    public static function loginHeadingHtml(): HtmlString
    {
        return new HtmlString('Keep it <span class="underline">ti</span>dy. Get it <span class="underline">do</span>ne.');
    }

    public static function loginSubheading(): string
    {
        return 'Where tidy preparation meets done work, then "tido" (sleep).';
    }

    public static function loginSubheadingHtml(): HtmlString
    {
        return new HtmlString(
            'Where <span class="underline">ti</span>dy preparation meets <span class="underline">do</span>ne work, then "<span class="underline">tido</span>" (sleep).',
        );
    }

    public static function dashboardActionPhrase(): string
    {
        return 'Start by tidying up your files, then get it done.';
    }

    public static function dashboardActionPhraseHtml(): HtmlString
    {
        return new HtmlString(
            'Start by <span class="underline">ti</span>dying up your files, then get it <span class="underline">do</span>ne.',
        );
    }
}
