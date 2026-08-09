<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;

class NotFound extends SimplePage
{
    public function hasTopbar(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Page Not Found';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Page Not Found';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'The requested page could not be found. It may have been moved, or the address may be incorrect.';
    }

    public function content(Schema $schema): Schema
    {
        $isAuthenticated = Filament::auth()->check();

        return $schema
            ->components([
                Actions::make([
                    Action::make($isAuthenticated ? 'returnToHome' : 'returnToLogin')
                        ->label($isAuthenticated ? 'Return to Home' : 'Return to Login')
                        ->url(
                            $isAuthenticated
                                ? (Filament::getUrl() ?? url('/admin'))
                                : (Filament::getLoginUrl() ?? url('/admin/login'))
                        )
                        ->button()
                        ->color('primary'),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(),
            ]);
    }
}
