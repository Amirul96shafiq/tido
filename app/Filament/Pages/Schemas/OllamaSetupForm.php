<?php

declare(strict_types=1);

namespace App\Filament\Pages\Schemas;

use App\Enums\OllamaDetectionState;
use App\Filament\Pages\OllamaPage;
use App\Services\Ollama\PopplerDetector;
use App\Support\ClipboardCopy;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

class OllamaSetupForm
{
    /**
     * @return list<Component|Action>
     */
    public static function components(): array
    {
        return [
            self::detectFieldset(),
            self::connectionFieldset(),
            self::downloadModelFieldset(),
            self::selectModelFieldset(),
            self::popplerFieldset(),
            self::advancedFieldset(),
        ];
    }

    private static function detectFieldset(): Fieldset
    {
        return Fieldset::make('01: Detect Ollama')
            ->schema([
                View::make('filament.pages.partials.ollama-detection-status')
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('downloadOllama')
                        ->label('Download Ollama for Windows')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->url('https://ollama.com/download', shouldOpenInNewTab: true)
                        ->visible(fn (OllamaPage $livewire): bool => $livewire->detectionState === OllamaDetectionState::NotInstalled->value),
                    Action::make('tryStartOllama')
                        ->label('Try start Ollama')
                        ->visible(fn (OllamaPage $livewire): bool => $livewire->detectionState === OllamaDetectionState::InstalledStopped->value)
                        ->action(function (OllamaPage $livewire, Get $get): void {
                            $livewire->applyHostFromForm((string) $get('host'));
                            $livewire->tryStartOllama();
                        }),
                    Action::make('testConnection')
                        ->label('Test connection')
                        ->color('gray')
                        ->visible(fn (OllamaPage $livewire): bool => in_array($livewire->detectionState, [
                            OllamaDetectionState::RemoteUnreachable->value,
                            OllamaDetectionState::Running->value,
                        ], true))
                        ->action(function (OllamaPage $livewire, Get $get): void {
                            $livewire->testConnection((string) $get('host'));
                        }),
                    Action::make('recheckDetection')
                        ->label(fn (OllamaPage $livewire): string => $livewire->detectionState === OllamaDetectionState::NotInstalled->value
                            ? "I've installed — Recheck"
                            : 'Recheck')
                        ->color('gray')
                        ->action(function (OllamaPage $livewire, Get $get): void {
                            $livewire->recheckDetection((string) $get('host'));
                        }),
                ])->columnSpanFull(),
            ]);
    }

    private static function connectionFieldset(): Fieldset
    {
        return Fieldset::make('02: Ollama Connection')
            ->schema([
                TextInput::make('host')
                    ->label('Host URL')
                    ->url()
                    ->required()
                    ->placeholder('http://127.0.0.1:11434')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rules(['required', 'url'])
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('testConnectionFromHost')
                        ->label('Test connection')
                        ->color('gray')
                        ->action(function (OllamaPage $livewire, Get $get): void {
                            $livewire->testConnection((string) $get('host'));
                        }),
                ])->columnSpanFull(),
            ]);
    }

    private static function downloadModelFieldset(): Fieldset
    {
        return Fieldset::make('03: Install vision model')
            ->visible(fn (OllamaPage $livewire): bool => $livewire->showModelDownloadStep())
            ->schema([
                TextInput::make('pull_command')
                    ->label('Terminal command')
                    ->readOnly()
                    ->dehydrated(false)
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->suffixAction(
                        Action::make('copyPullCommand')
                            ->label('Copy')
                            ->tooltip('Copy')
                            ->icon(Heroicon::ClipboardDocumentList)
                            ->color('gray')
                            ->alpineClickHandler(function (mixed $state): string {
                                return ClipboardCopy::alpineClickHandler(
                                    (string) $state,
                                    'Copied',
                                );
                            }),
                    )
                    ->helperText('Run this command in a terminal on this PC. Recheck after the pull completes. The recommended vision model is several gigabytes.')
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('recheckAfterModelPull')
                        ->label("I've pulled — Recheck")
                        ->color('gray')
                        ->action(function (OllamaPage $livewire, Get $get): void {
                            $livewire->recheckDetection((string) $get('host'));
                        }),
                ])->columnSpanFull(),
            ]);
    }

    private static function selectModelFieldset(): Fieldset
    {
        return Fieldset::make('04: Choose Ollama model')
            ->visible(fn (OllamaPage $livewire): bool => $livewire->showModelSelectStep())
            ->schema([
                Select::make('selectedModel')
                    ->label('Model')
                    ->options(fn (OllamaPage $livewire): array => collect($livewire->availableModels)
                        ->mapWithKeys(static fn (array $model): array => [$model['name'] => $model['name']])
                        ->all())
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->columnSpanFull()
                    ->rules(['required', 'string'])
                    ->afterStateUpdated(function (?string $state, OllamaPage $livewire): void {
                        if (filled($state)) {
                            $livewire->handleModelSelection($state);
                        }
                    })
                    ->helperText('Vision models such as qwen2.5vl:7b work best for receipt OCR.'),
            ]);
    }

    private static function popplerFieldset(): Fieldset
    {
        return Fieldset::make('05: PDF processing (optional)')
            ->visible(fn (OllamaPage $livewire): bool => $livewire->detectionState === OllamaDetectionState::Running->value)
            ->schema([
                View::make('filament.pages.partials.ollama-poppler-guide')
                    ->key('popplerGuide')
                    ->columnSpanFull(),
                View::make('filament.pages.partials.ollama-poppler-empty-guide')
                    ->key('popplerEmptyGuide')
                    ->visible(fn (Get $get): bool => self::popplerBinariesMissing($get))
                    ->columnSpanFull(),
                TextInput::make('pdfinfo_binary')
                    ->label('pdfinfo binary')
                    ->placeholder('C:\path\to\poppler\Library\bin\pdfinfo.exe')
                    ->live()
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->columnSpanFull(),
                TextInput::make('pdftocairo_binary')
                    ->label('pdftocairo binary')
                    ->placeholder('C:\path\to\poppler\Library\bin\pdftocairo.exe')
                    ->live()
                    ->extraInputAttributes(['class' => 'font-mono']),
                TextInput::make('pdftotext_binary')
                    ->label('pdftotext binary')
                    ->placeholder('C:\path\to\poppler\Library\bin\pdftotext.exe')
                    ->live()
                    ->extraInputAttributes(['class' => 'font-mono']),
                Actions::make([
                    Action::make('downloadPoppler')
                        ->label('Download Poppler for Windows')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->url(PopplerDetector::WINDOWS_DOWNLOAD_URL, shouldOpenInNewTab: true)
                        ->visible(fn (Get $get): bool => self::popplerBinariesMissing($get)),
                    Action::make('detectPopplerBinaries')
                        ->label('Auto-detect Poppler')
                        ->color('gray')
                        ->action(function (OllamaPage $livewire, Set $set): void {
                            $livewire->detectPopplerBinaries();
                            $set('pdfinfo_binary', $livewire->pdfInfoBinary);
                            $set('pdftocairo_binary', $livewire->pdfToCairoBinary);
                            $set('pdftotext_binary', $livewire->pdfToTextBinary);
                        }),
                    Action::make('skipPoppler')
                        ->label('Skip for now')
                        ->color('gray')
                        ->action(fn (OllamaPage $livewire) => $livewire->skipPoppler()),
                ])->columnSpanFull(),
            ])
            ->columns(2);
    }

    private static function popplerBinariesMissing(Get $get): bool
    {
        return blank($get('pdfinfo_binary'))
            && blank($get('pdftocairo_binary'))
            && blank($get('pdftotext_binary'));
    }

    private static function advancedFieldset(): Fieldset
    {
        return Fieldset::make('06: Advanced settings')
            ->visible(fn (OllamaPage $livewire): bool => $livewire->detectionState === OllamaDetectionState::Running->value)
            ->schema([
                TextInput::make('timeout')
                    ->label('Timeout (seconds)')
                    ->integer()
                    ->required()
                    ->minValue(30)
                    ->maxValue(600)
                    ->helperText('30–600 seconds. HTTP wait for receipt extraction.'),
                TextInput::make('num_ctx')
                    ->label('Context window')
                    ->integer()
                    ->required()
                    ->minValue(2048)
                    ->maxValue(131072)
                    ->helperText('2,048–131,072 tokens. Prompt and JSON answer budget.'),
                TextInput::make('max_image_dimension')
                    ->label('Max image dimension')
                    ->integer()
                    ->required()
                    ->minValue(512)
                    ->maxValue(4096)
                    ->helperText('512–4,096 px. Long-edge resize before OCR.'),
            ])
            ->columns(3);
    }
}
