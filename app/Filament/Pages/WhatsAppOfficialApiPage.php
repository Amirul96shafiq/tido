<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Pages\Concerns\RendersComingSoonIntegration;
use App\Filament\Support\IntegrationNavigation;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class WhatsAppOfficialApiPage extends Page
{
    use PrependsHomeBreadcrumb;
    use RendersComingSoonIntegration;
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $slug = 'whatsapp-official-api';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCloud;

    protected static ?string $navigationLabel = 'Official API';

    protected static ?string $navigationParentItem = IntegrationNavigation::WHATSAPP;

    protected static string|\UnitEnum|null $navigationGroup = IntegrationNavigation::GROUP;

    protected static ?string $title = 'Official API';

    protected static ?int $navigationSort = 20;

    /**
     * @return array{id: string, heading: string, icon: string, description: string}
     */
    public static function comingSoonViewData(): array
    {
        return [
            'id' => 'whatsapp-official-api-overview',
            'heading' => 'Official API',
            'icon' => 'heroicon-o-cloud',
            'description' => 'The WhatsApp Official API is not available as a messaging integration yet.',
        ];
    }
}
