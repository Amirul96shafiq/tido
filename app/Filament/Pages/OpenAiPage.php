<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Pages\Concerns\RendersComingSoonIntegration;
use App\Filament\Support\IntegrationNavigation;
use Filament\Pages\Page;

class OpenAiPage extends Page
{
    use PrependsHomeBreadcrumb;
    use RendersComingSoonIntegration;
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $slug = 'openai';

    protected static string|\BackedEnum|null $navigationIcon = 'icon-openai';

    protected static ?string $navigationLabel = 'OpenAI';

    protected static ?string $navigationParentItem = IntegrationNavigation::AI_PARSING_ENGINE;

    protected static string|\UnitEnum|null $navigationGroup = IntegrationNavigation::GROUP;

    protected static ?string $title = 'OpenAI';

    protected static ?int $navigationSort = 30;

    /**
     * @return array{id: string, heading: string, icon: string, description: string}
     */
    public static function comingSoonViewData(): array
    {
        return [
            'id' => 'openai-overview',
            'heading' => 'OpenAI',
            'icon' => 'icon-openai',
            'description' => 'OpenAI is not available as a parsing engine yet.',
        ];
    }
}
