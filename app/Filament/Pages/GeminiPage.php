<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Pages\Concerns\RendersComingSoonIntegration;
use App\Filament\Support\IntegrationNavigation;
use Filament\Pages\Page;

class GeminiPage extends Page
{
    use PrependsHomeBreadcrumb;
    use RendersComingSoonIntegration;
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $slug = 'gemini';

    protected static string|\BackedEnum|null $navigationIcon = 'icon-gemini';

    protected static ?string $navigationLabel = 'Gemini';

    protected static ?string $navigationParentItem = IntegrationNavigation::AI_PARSING_ENGINE;

    protected static string|\UnitEnum|null $navigationGroup = IntegrationNavigation::GROUP;

    protected static ?string $title = 'Gemini';

    protected static ?int $navigationSort = 10;

    /**
     * @return array{id: string, heading: string, icon: string, description: string}
     */
    public static function comingSoonViewData(): array
    {
        return [
            'id' => 'gemini-overview',
            'heading' => 'Gemini',
            'icon' => 'icon-gemini',
            'description' => 'Google Gemini is not available as a parsing engine yet.',
        ];
    }
}
