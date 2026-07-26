<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;
use App\Support\TimeOfDayGreeting;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

trait HasDashboardGreeting
{
    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'tido-dashboard-greeting',
            'tido-dashboard-page',
        ];
    }

    public function getHeading(): string|Htmlable
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return parent::getHeading();
        }

        $now = now()->timezone($user->preferredTimezone());
        $greetingName = filled($user->display_name)
            ? (string) $user->display_name
            : $user->name;

        return TimeOfDayGreeting::headingHtmlFor($now, $greetingName);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return parent::getSubheading();
        }

        $now = now()->timezone($user->preferredTimezone());

        return TimeOfDayGreeting::subheadingHtml($now);
    }
}
