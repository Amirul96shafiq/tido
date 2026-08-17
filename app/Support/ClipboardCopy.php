<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Js;

final class ClipboardCopy
{
    /**
     * Alpine click handler for copy CTAs.
     *
     * Delegates to `window.tidoCopyToClipboard` (resources/js/clipboard-copy.js)
     * because `navigator.clipboard` is undefined on insecure origins such as
     * http://tido.local, where it would otherwise throw and copy nothing.
     */
    public static function alpineClickHandler(
        string $value,
        string $successTitle,
        string $failureTitle = 'Copy failed, copy it manually',
    ): string {
        return sprintf(
            'window.tidoCopyToClipboard(%s).then((copied) => copied '
                .'? new FilamentNotification().title(%s).success().send() '
                .': new FilamentNotification().title(%s).danger().send())',
            Js::from($value),
            Js::from($successTitle),
            Js::from($failureTitle),
        );
    }
}
