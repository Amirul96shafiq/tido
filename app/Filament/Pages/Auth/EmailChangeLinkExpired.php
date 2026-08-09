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

class EmailChangeLinkExpired extends SimplePage
{
    public function getTitle(): string|Htmlable
    {
        return 'Verification Link Expired';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Verification Link Expired';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'The verification link to change your email address has expired. For your security, email change verification links are only valid for a limited time.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Actions::make([
                    Action::make('returnToProfile')
                        ->label('Return to Profile Settings')
                        ->url(Filament::getProfileUrl() ?? url('/admin/profile'))
                        ->button()
                        ->color('primary'),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(),
            ]);
    }
}
