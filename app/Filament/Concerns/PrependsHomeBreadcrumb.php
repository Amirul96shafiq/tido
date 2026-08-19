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
 * Sidebar parent labels (for example WhatsApp under Integrations) are inserted
 * as unlinked crumbs — they are not pages.
 *
 * Example: Home > Expenses > List
 * Example: Home > WhatsApp > Evolution API
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
                $breadcrumbs[] = $label;
            } elseif (filled($label)) {
                $breadcrumbs[] = (string) $label;
            }
        }

        if ($this instanceof BaseDashboard) {
            return $breadcrumbs === [] ? ['Home'] : $breadcrumbs;
        }

        $crumbs = [
            Dashboard::getUrl() => 'Home',
        ];

        $parentItem = static::getNavigationParentItem();

        if (is_string($parentItem) && filled($parentItem)) {
            $crumbs[] = $parentItem;
        }

        return [
            ...$crumbs,
            ...$breadcrumbs,
        ];
    }
}
