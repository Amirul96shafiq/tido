<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Illuminate\Contracts\View\View;

trait HasDashboardWidgetPlaceholder
{
    public function placeholder(): View
    {
        return view('filament.widgets.lazy-placeholder', [
            'height' => $this->getPlaceholderHeight(),
            ...$this->getPlaceholderData(),
        ]);
    }
}
