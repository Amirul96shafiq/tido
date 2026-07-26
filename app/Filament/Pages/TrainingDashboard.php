<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasDashboardGreeting;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class TrainingDashboard extends Page
{
    use HasDashboardGreeting;
    use PrependsHomeBreadcrumb;

    protected static ?string $slug = 'training';

    protected static ?string $title = 'Training';

    protected static bool $shouldRegisterNavigation = false;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.pages.partials.training-dashboard-content'),
            ]);
    }
}
