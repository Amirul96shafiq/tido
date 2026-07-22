<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Pages\Dashboard;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Page as FilamentPage;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Prepends a Home crumb linking to the panel Dashboard.
 *
 * Example: Home > Invoices > List
 */
trait PrependsHomeBreadcrumb
{
    /**
     * @return array<string|int, string|Htmlable>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();

        if ($breadcrumbs === [] && $this instanceof FilamentPage) {
            // Custom Filament pages have getTitle() but not getBreadcrumb()
            // (that method exists only on resource pages).
            $label = method_exists($this, 'getBreadcrumb')
                ? ($this->getBreadcrumb() ?? $this->getTitle())
                : $this->getTitle();

            if ($label instanceof Htmlable) {
                $label = $label->toHtml();
            }

            if (filled($label)) {
                $breadcrumbs[] = (string) $label;
            }
        }

        if ($this instanceof BaseDashboard) {
            return $breadcrumbs === [] ? ['Home'] : $breadcrumbs;
        }

        return [
            Dashboard::getUrl() => 'Home',
            ...$breadcrumbs,
        ];
    }
}
