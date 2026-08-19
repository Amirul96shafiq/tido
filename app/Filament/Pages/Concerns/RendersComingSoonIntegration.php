<?php

declare(strict_types=1);

namespace App\Filament\Pages\Concerns;

use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

trait RendersComingSoonIntegration
{
    public static function getNavigationBadge(): ?string
    {
        return 'Coming soon';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.pages.partials.coming-soon-dashboard-content')
                    ->viewData(static::comingSoonViewData()),
            ]);
    }

    /**
     * @return array{id: string, heading: string, icon: string, description: string}
     */
    abstract public static function comingSoonViewData(): array;
}
