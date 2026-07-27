<?php

declare(strict_types=1);

namespace App\Filament\Notifications;

use Filament\Notifications\Notification as BaseNotification;

class Notification extends BaseNotification
{
    public function toEmbeddedHtml(): string
    {
        $html = parent::toEmbeddedHtml();

        if ($this->isInline()) {
            return $html;
        }

        $duration = $this->getDuration();

        if ($duration === 'persistent') {
            return $html;
        }

        $timerHtml = sprintf(
            '<div class="tido-no-timer" style="--tido-no-duration: %dms" aria-hidden="true"><div class="tido-no-timer-bar"></div></div>',
            (int) $duration,
        );

        $closingTagPosition = strrpos($html, '</div>');

        if ($closingTagPosition === false) {
            return $html;
        }

        return substr($html, 0, $closingTagPosition).$timerHtml.substr($html, $closingTagPosition);
    }
}
