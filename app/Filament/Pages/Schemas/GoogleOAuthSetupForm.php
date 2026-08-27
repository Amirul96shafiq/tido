<?php

declare(strict_types=1);

namespace App\Filament\Pages\Schemas;

use App\Filament\Pages\GoogleOAuthPage;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

class GoogleOAuthSetupForm
{
    /**
     * @return list<Component|Action>
     */
    public static function components(): array
    {
        return [
            self::cloudConsoleFieldset(),
            self::oauthClientFieldset(),
            self::enableSignInFieldset(),
        ];
    }

    private static function cloudConsoleFieldset(): Fieldset
    {
        return Fieldset::make('01: Google Cloud Console')
            ->schema([
                View::make('filament.pages.partials.google-oauth-cloud-console')
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('openGoogleCloud')
                        ->label('Open Google Cloud Console')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url('https://console.cloud.google.com/apis/credentials', shouldOpenInNewTab: true),
                    Action::make('openConsentScreen')
                        ->label('Open consent screen')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->color('gray')
                        ->url('https://console.cloud.google.com/apis/credentials/consent', shouldOpenInNewTab: true),
                ])->columnSpanFull(),
            ]);
    }

    private static function oauthClientFieldset(): Fieldset
    {
        return Fieldset::make('02: OAuth Client')
            ->schema([
                Hidden::make('has_saved_secret'),
                TextInput::make('client_id')
                    ->label('Client ID')
                    ->placeholder('Paste the Web client ID')
                    ->required()
                    ->maxLength(255),
                TextInput::make('client_secret')
                    ->label('Client Secret')
                    ->password()
                    ->revealable()
                    ->placeholder('Paste the Web client secret')
                    ->required(fn (Get $get): bool => ! (bool) $get('has_saved_secret'))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (Get $get): ?string => (bool) $get('has_saved_secret')
                        ? 'Leave blank to keep the saved secret.'
                        : null),
                View::make('filament.pages.partials.google-oauth-redirect-uri')
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('testCredentials')
                        ->label('Test credentials')
                        ->icon('heroicon-o-signal')
                        ->color('gray')
                        ->action(function (GoogleOAuthPage $livewire, Get $get): void {
                            $livewire->testCredentialsFromForm(
                                (string) $get('client_id'),
                                (string) $get('client_secret'),
                            );
                        }),
                ])->columnSpanFull(),
            ]);
    }

    private static function enableSignInFieldset(): Fieldset
    {
        return Fieldset::make('03: Enable Sign-In')
            ->schema([
                Toggle::make('enabled')
                    ->label('Show Continue with Google on the login page')
                    ->inline(false)
                    ->default(false),
            ]);
    }
}
