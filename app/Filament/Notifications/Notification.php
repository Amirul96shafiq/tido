<?php

declare(strict_types=1);

namespace App\Filament\Notifications;

use Filament\Notifications\Notification as BaseNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Notification extends BaseNotification
{
    /**
     * @param  Model|Authenticatable|Collection|array<Model|Authenticatable>  $users
     */
    public function sendToDatabase(Model|Authenticatable|Collection|array $users, bool $isEventDispatched = true): static
    {
        return parent::sendToDatabase($users, isEventDispatched: true);
    }

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
