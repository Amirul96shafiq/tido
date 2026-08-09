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

class PasswordResetLinkExpired extends SimplePage
{
    public function hasTopbar(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Password Reset Link Expired';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Link Expired';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'The password reset link has expired. For your security, password reset links are only valid for a limited time. Request a new link from the login page to continue.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Actions::make([
                    Action::make('returnToLogin')
                        ->label('Return to Login')
                        ->url(Filament::getLoginUrl() ?? url('/admin/login'))
                        ->button()
                        ->color('primary'),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(),
            ]);
    }
}
