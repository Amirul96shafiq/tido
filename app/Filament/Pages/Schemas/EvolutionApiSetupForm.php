<?php

declare(strict_types=1);

namespace App\Filament\Pages\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

final class EvolutionApiSetupForm
{
    /**
     * @return list<Component>
     */
    public static function components(): array
    {
        return [
            Fieldset::make('Evolution API connection')
                ->schema([
                    TextInput::make('api_url')
                        ->label('API URL')
                        ->url()
                        ->required()
                        ->placeholder('http://127.0.0.1:8080')
                        ->extraInputAttributes(['class' => 'font-mono']),
                    TextInput::make('instance_name')
                        ->label('Instance name')
                        ->required()
                        ->maxLength(64)
                        ->extraInputAttributes(['class' => 'font-mono']),
                    TextInput::make('api_key')
                        ->label('API key')
                        ->password()
                        ->revealable()
                        ->placeholder('Paste a new API key')
                        ->required(fn (Get $get): bool => ! (bool) $get('has_saved_api_key'))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (Get $get): ?string => (bool) $get('has_saved_api_key')
                            ? 'Leave blank to keep the saved key.'
                            : null),
                    TextInput::make('webhook_secret')
                        ->label('Webhook secret')
                        ->password()
                        ->revealable()
                        ->placeholder('Paste a new webhook secret')
                        ->required(fn (Get $get): bool => ! (bool) $get('has_saved_webhook_secret'))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (Get $get): ?string => (bool) $get('has_saved_webhook_secret')
                            ? 'Leave blank to keep the saved secret.'
                            : null),
                    Hidden::make('whatsapp_enabled')
                        ->dehydrated(false),
                    Hidden::make('has_saved_api_key'),
                    Hidden::make('has_saved_webhook_secret'),
                ])
                ->columns(2),
        ];
    }
}
