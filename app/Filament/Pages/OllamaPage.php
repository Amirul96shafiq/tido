<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\OllamaDetectionState;
use App\Filament\Concerns\HasSectionNav;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Pages\Schemas\OllamaSetupForm;
use App\Models\Expense;
use App\Prompts\ReceiptExtractionPrompt;
use App\Services\Ollama\OllamaDetector;
use App\Services\Ollama\OllamaSettings;
use App\Services\Ollama\OllamaTagsClient;
use App\Services\Ollama\PopplerDetector;
use App\Services\OllamaService;
use App\Services\ReceiptImagePreparer;
use App\Support\OllamaVisionModel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Throwable;

class OllamaPage extends Page
{
    use HasSectionNav;
    use PrependsHomeBreadcrumb;
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $slug = 'ollama';

    protected static string|\BackedEnum|null $navigationIcon = 'icon-ollama';

    protected static ?string $navigationLabel = 'Ollama';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $title = 'Ollama';

    protected static ?int $navigationSort = 10;

    public string $detectionState = OllamaDetectionState::NotInstalled->value;

    public string $detectionMessage = '';

    public string $connectionStatus = 'unknown';

    public string $statusMessage = '';

    public int $latencyMs = 0;

    public string $configuredModel = '';

    public string $selectedModel = '';

    public string $host = '';

    public int $timeout = 120;

    public int $numCtx = 8192;

    public int $maxImageDimension = 1280;

    public string $pdfInfoBinary = '';

    public string $pdfToCairoBinary = '';

    public string $pdfToTextBinary = '';

    public bool $popplerSkipped = false;

    public bool $usingSavedSettings = false;

    public bool $setupComplete = false;

    /**
     * @var array{success: bool, message: string, merchant: string|null, total: string|null}|null
     */
    public ?array $testExtractionResult = null;

    public bool $testExtractionRunning = false;

    /**
     * @var list<array{name: string, family: string, parameterSize: string, quantization: string, contextLength: int, sizeBytes: int, isConfigured: bool}>
     */
    public array $availableModels = [];

    /**
     * @var list<array{label: string, status: string, detail: string}>
     */
    public array $pipelineChecks = [];

    /**
     * @var list<array{label: string, value: string, description: string}>
     */
    public array $activityStats = [];

    public string $latestReceiptActivity = 'No processed receipts yet.';

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-ollama-page',
        ];
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Status', 'id' => 'ollama-status'],
            ['label' => 'Pipeline Readiness', 'id' => 'ollama-pipeline'],
            ['label' => 'Receipt & Parsing Activity', 'id' => 'ollama-activity'],
        ];
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Ollama sections';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->wrapInSectionNavScope([
                    SchemaView::make('filament.pages.partials.ollama-content'),
                ]),
            ]);
    }

    public function mount(
        OllamaSettings $settings,
        OllamaDetector $detector,
        PopplerDetector $popplerDetector,
    ): void {
        $this->loadFromSettings($settings);
        $this->runDetection($detector);
        $this->detectPoppler($popplerDetector);
        $this->fetchStatus(app(OllamaTagsClient::class));
        $this->loadPipelineChecks();
        $this->loadActivityStats();
    }

    public function settingsSourceLabel(): string
    {
        if ($this->setupComplete) {
            return 'Setup complete';
        }

        if ($this->usingSavedSettings) {
            return 'Using saved settings';
        }

        return 'Using environment defaults';
    }

    public function configureSetupAction(): Action
    {
        return Action::make('configureSetup')
            ->label(fn (): string => $this->setupComplete ? 'Edit setup' : 'Configure')
            ->color('gray')
            ->modalHeading('Setup')
            ->modalDescription('Configure Ollama for receipt parsing and health checks.')
            ->modalSubmitActionLabel('Save settings')
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(fn (): array => $this->setupFormState())
            ->schema(OllamaSetupForm::components())
            ->modalFooterActions(function (Action $action): array {
                $runTestExtraction = Action::make('runTestExtraction')
                    ->label('Run test extraction')
                    ->visible(fn (): bool => $this->detectionState === OllamaDetectionState::Running->value
                        && filled($this->selectedModel))
                    ->action(function (): void {
                        $this->runTestExtraction();
                    });

                return [
                    $runTestExtraction,
                    $action->getModalSubmitAction(),
                    $action->getModalCancelAction(),
                ];
            })
            ->action(function (array $data, Action $action): void {
                if (! $this->saveSettingsFromState($data)) {
                    $action->halt();
                }
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $this->runDetection(app(OllamaDetector::class));
                    $this->fetchStatus(app(OllamaTagsClient::class));
                    $this->loadPipelineChecks();
                    $this->loadActivityStats();

                    Notification::make()
                        ->title('Status refreshed')
                        ->success()
                        ->send();
                }),
            $this->configureSetupAction(),
        ];
    }

    public function applyHostFromForm(string $host): void
    {
        if (filled($host)) {
            $this->host = rtrim($host, '/');
        }
    }

    public function recheckDetection(?string $host = null): void
    {
        $this->applyHostFromForm((string) $host);
        $this->runDetection(app(OllamaDetector::class));
        $this->fetchStatus(app(OllamaTagsClient::class));
        $this->loadPipelineChecks();
        $this->syncMountedSetupForm();
    }

    public function tryStartOllama(): void
    {
        $started = app(OllamaDetector::class)->tryStart();

        if ($started) {
            Notification::make()
                ->title('Ollama started')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Could not start Ollama')
                ->body('Start the Ollama Windows service manually, then recheck.')
                ->warning()
                ->send();
        }

        $this->recheckDetection();
    }

    public function testConnection(?string $host = null): void
    {
        $this->recheckDetection($host);

        $notification = Notification::make()
            ->title($this->connectionStatus === 'operational' ? 'Connection successful' : 'Connection failed')
            ->body($this->statusMessage);

        if ($this->connectionStatus === 'operational') {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }

    public function handleModelSelection(string $modelName): void
    {
        $this->applySelectedModel($modelName, syncForm: false);

        if (! OllamaVisionModel::isLikelyVisionModel($modelName)) {
            Notification::make()
                ->title('Vision model recommended')
                ->body('Receipt image parsing works best with a vision model such as qwen2.5vl:7b.')
                ->warning()
                ->send();
        }
    }

    public function skipPoppler(): void
    {
        $this->popplerSkipped = true;

        Notification::make()
            ->title('Poppler skipped')
            ->body('Image receipts will still parse. PDF receipts need Poppler later.')
            ->warning()
            ->send();
    }

    public function detectPopplerBinaries(): void
    {
        $result = app(PopplerDetector::class)->probe();

        if ($result['pdfinfo'] !== null) {
            $this->pdfInfoBinary = $result['pdfinfo'];
        }

        if ($result['pdftocairo'] !== null) {
            $this->pdfToCairoBinary = $result['pdftocairo'];
        }

        if ($result['pdftotext'] !== null) {
            $this->pdfToTextBinary = $result['pdftotext'];
        }

        $this->syncMountedSetupForm();

        if ($result['allFound']) {
            Notification::make()
                ->title('Poppler detected')
                ->success()
                ->send();

            return;
        }

        $missing = array_keys(array_filter([
            'pdfinfo' => $result['pdfinfo'] === null,
            'pdftocairo' => $result['pdftocairo'] === null,
            'pdftotext' => $result['pdftotext'] === null,
        ]));

        if (count($missing) === 3) {
            Notification::make()
                ->title('Failed to find Poppler')
                ->body('pdfinfo, pdftocairo, and pdftotext were not on PATH.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Poppler partially detected')
            ->body('Missing on PATH: '.implode(', ', $missing).'.')
            ->warning()
            ->send();
    }

    public function runTestExtraction(): void
    {
        $this->testExtractionRunning = true;
        $this->testExtractionResult = null;

        $fixturePath = resource_path('fixtures/receipts/sample.jpg');

        if (! File::exists($fixturePath)) {
            $this->testExtractionResult = [
                'success' => false,
                'message' => 'Sample receipt fixture is missing.',
                'merchant' => null,
                'total' => null,
            ];
            $this->testExtractionRunning = false;

            return;
        }

        try {
            $imagePreparer = app(ReceiptImagePreparer::class);
            $base64 = $imagePreparer->toBase64(File::get($fixturePath));
            $parsed = app(OllamaService::class)->parseReceipt(
                $base64,
                ReceiptExtractionPrompt::build(),
            );

            if ($parsed === null) {
                $this->testExtractionResult = [
                    'success' => false,
                    'message' => 'Test extraction returned no structured JSON.',
                    'merchant' => null,
                    'total' => null,
                ];
            } else {
                $this->testExtractionResult = [
                    'success' => true,
                    'message' => 'Test extraction completed successfully.',
                    'merchant' => isset($parsed['merchant_name']) ? (string) $parsed['merchant_name'] : null,
                    'total' => isset($parsed['total_amount']) ? (string) $parsed['total_amount'] : null,
                ];
            }
        } catch (Throwable $throwable) {
            $this->testExtractionResult = [
                'success' => false,
                'message' => $throwable->getMessage(),
                'merchant' => null,
                'total' => null,
            ];
        }

        $this->testExtractionRunning = false;

        if ($this->testExtractionResult === null) {
            return;
        }

        if ($this->testExtractionResult['success']) {
            Notification::make()
                ->title('Test extraction succeeded')
                ->body(collect([
                    $this->testExtractionResult['message'],
                    filled($this->testExtractionResult['merchant']) ? 'Merchant: '.$this->testExtractionResult['merchant'] : null,
                    filled($this->testExtractionResult['total']) ? 'Total: '.$this->testExtractionResult['total'] : null,
                ])->filter()->implode(' · '))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Test extraction failed')
            ->body($this->testExtractionResult['message'])
            ->danger()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function saveSettingsFromState(array $state): bool
    {
        $this->applySetupFormState($state);

        $host = rtrim($this->host, '/');
        $modelNames = app(OllamaTagsClient::class)->modelNames($host);

        if (! in_array($this->selectedModel, $modelNames, true)) {
            Notification::make()
                ->title('Selected model is not installed')
                ->body('Download the model or pick one from the detected list.')
                ->danger()
                ->send();

            return false;
        }

        app(OllamaSettings::class)->save([
            'host' => $host,
            'model' => $this->selectedModel,
            'timeout' => $this->timeout,
            'num_ctx' => $this->numCtx,
            'max_image_dimension' => $this->maxImageDimension,
            'pdfinfo_binary' => filled($this->pdfInfoBinary) ? $this->pdfInfoBinary : null,
            'pdftocairo_binary' => filled($this->pdfToCairoBinary) ? $this->pdfToCairoBinary : null,
            'pdftotext_binary' => filled($this->pdfToTextBinary) ? $this->pdfToTextBinary : null,
            'setup_completed_at' => now(),
        ]);

        $this->loadFromSettings(app(OllamaSettings::class));
        $this->configuredModel = $this->selectedModel;
        $this->fetchStatus(app(OllamaTagsClient::class));
        $this->loadPipelineChecks();

        Notification::make()
            ->title('Ollama settings saved')
            ->success()
            ->send();

        return true;
    }

    public function formattedSize(int $bytes): string
    {
        if ($bytes >= 1_000_000_000) {
            return number_format($bytes / 1_000_000_000, 1).' GB';
        }

        if ($bytes >= 1_000_000) {
            return number_format($bytes / 1_000_000, 1).' MB';
        }

        return number_format($bytes / 1_000, 1).' KB';
    }

    public function installedModelCount(): int
    {
        return count($this->availableModels);
    }

    /**
     * @return array{name: string, family: string, parameterSize: string, quantization: string, contextLength: int, sizeBytes: int, isConfigured: bool}|null
     */
    public function activeModel(): ?array
    {
        $configured = collect($this->availableModels)->first(
            static fn (array $model): bool => $model['isConfigured'],
        );

        if (is_array($configured)) {
            return $configured;
        }

        $selected = collect($this->availableModels)->first(
            fn (array $model): bool => $model['name'] === $this->selectedModel
                || $model['name'] === $this->configuredModel,
        );

        return is_array($selected) ? $selected : null;
    }

    public function readyPipelineCheckCount(): int
    {
        return count(array_filter(
            $this->pipelineChecks,
            static fn (array $check): bool => $check['status'] === 'ready',
        ));
    }

    public function showModelDownloadStep(): bool
    {
        return $this->detectionState === OllamaDetectionState::Running->value
            && count($this->availableModels) === 0;
    }

    public function showModelSelectStep(): bool
    {
        return $this->detectionState === OllamaDetectionState::Running->value
            && count($this->availableModels) > 0;
    }

    /**
     * @return list<array{heading: string, items: list<string>}>
     */
    public function supportedTaskGroups(): array
    {
        return [
            [
                'heading' => 'Current use in tido',
                'items' => [
                    'Receipt image extraction',
                    'PDF receipt page extraction and merge',
                    'Manual WhatsApp expense label suggestions',
                ],
            ],
            [
                'heading' => 'Also suitable for',
                'items' => [
                    'Invoice OCR and document capture',
                    'Delivery order and packing slip extraction',
                    'Serial number and warranty note capture',
                    'Local document summarisation workflows',
                ],
            ],
        ];
    }

    private function loadFromSettings(OllamaSettings $settings): void
    {
        $this->host = $settings->host();
        $this->configuredModel = $settings->model();
        $this->selectedModel = $settings->model();
        $this->timeout = $settings->timeout();
        $this->numCtx = $settings->numCtx();
        $this->maxImageDimension = $settings->maxImageDimension();
        $this->pdfInfoBinary = $settings->pdfinfoBinary();
        $this->pdfToCairoBinary = $settings->pdftocairoBinary();
        $this->pdfToTextBinary = $settings->pdftotextBinary();
        $this->usingSavedSettings = $settings->usesSavedSettings();
        $this->setupComplete = $settings->isSetupComplete();
    }

    private function runDetection(OllamaDetector $detector): void
    {
        $probe = $detector->probe($this->host);
        $this->detectionState = $probe['state']->value;
        $this->detectionMessage = $probe['message'];
        $this->latencyMs = $probe['latencyMs'];
    }

    private function detectPoppler(PopplerDetector $popplerDetector): void
    {
        if ($this->usingSavedSettings) {
            return;
        }

        $result = $popplerDetector->probe();

        if ($result['pdfinfo'] !== null) {
            $this->pdfInfoBinary = $result['pdfinfo'];
        }

        if ($result['pdftocairo'] !== null) {
            $this->pdfToCairoBinary = $result['pdftocairo'];
        }

        if ($result['pdftotext'] !== null) {
            $this->pdfToTextBinary = $result['pdftotext'];
        }
    }

    private function fetchStatus(OllamaTagsClient $tagsClient): void
    {
        $result = $tagsClient->fetch($this->host, $this->selectedModel);
        $this->latencyMs = $result['latencyMs'];

        if (! $result['success']) {
            $this->connectionStatus = 'down';
            $this->statusMessage = $result['message'];
            $this->availableModels = [];

            return;
        }

        $this->availableModels = $result['models'];
        $count = count($this->availableModels);

        if ($count === 0) {
            $this->connectionStatus = 'degraded';
            $this->statusMessage = 'Ollama is reachable but no models are installed.';
        } else {
            $this->connectionStatus = 'operational';
            $this->statusMessage = $count === 1
                ? 'Your Ollama instance is connected and ready. 1 model is installed and available for receipt parsing — run a test extraction anytime to confirm OCR and structured output.'
                : 'Your Ollama instance is connected and ready. '.$count.' models are installed and available for receipt parsing — run a test extraction anytime to confirm OCR and structured output.';
        }

        if ($this->detectionState === OllamaDetectionState::Running->value) {
            $this->autoSelectModel();
        }
    }

    private function autoSelectModel(): void
    {
        $selectedModel = $this->selectedModel;

        if ($selectedModel !== '' && collect($this->availableModels)->contains(
            static fn (array $model): bool => $model['name'] === $selectedModel,
        )) {
            $this->markActiveModel($selectedModel);

            return;
        }

        $recommended = collect($this->availableModels)->first(
            static fn (array $model): bool => $model['name'] === OllamaSettings::RECOMMENDED_VISION_MODEL,
        );

        if (is_array($recommended)) {
            $this->applySelectedModel($recommended['name']);

            return;
        }

        $firstVision = collect($this->availableModels)->first(
            static fn (array $model): bool => OllamaVisionModel::isLikelyVisionModel($model['name'], $model['family']),
        );

        if (is_array($firstVision)) {
            $this->applySelectedModel($firstVision['name']);

            return;
        }

        $first = $this->availableModels[0] ?? null;

        if (is_array($first)) {
            $this->applySelectedModel($first['name']);
        }
    }

    private function applySelectedModel(string $modelName, bool $syncForm = true): void
    {
        $this->selectedModel = $modelName;
        $this->configuredModel = $modelName;
        $this->markActiveModel($modelName);

        if ($syncForm) {
            $this->syncMountedSetupForm();
        }
    }

    private function syncMountedSetupForm(): void
    {
        $this->getMountedActionSchema()?->fill($this->setupFormState());
    }

    /**
     * @return array<string, mixed>
     */
    private function setupFormState(): array
    {
        return [
            'host' => $this->host,
            'selectedModel' => $this->selectedModel,
            'pull_command' => OllamaSettings::recommendedPullCommand(),
            'timeout' => $this->timeout,
            'num_ctx' => $this->numCtx,
            'max_image_dimension' => $this->maxImageDimension,
            'pdfinfo_binary' => $this->pdfInfoBinary,
            'pdftocairo_binary' => $this->pdfToCairoBinary,
            'pdftotext_binary' => $this->pdfToTextBinary,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function applySetupFormState(array $state): void
    {
        $this->host = rtrim((string) ($state['host'] ?? $this->host), '/');
        $this->selectedModel = (string) ($state['selectedModel'] ?? $this->selectedModel);
        $this->timeout = (int) ($state['timeout'] ?? $this->timeout);
        $this->numCtx = (int) ($state['num_ctx'] ?? $this->numCtx);
        $this->maxImageDimension = (int) ($state['max_image_dimension'] ?? $this->maxImageDimension);
        $this->pdfInfoBinary = (string) ($state['pdfinfo_binary'] ?? $this->pdfInfoBinary);
        $this->pdfToCairoBinary = (string) ($state['pdftocairo_binary'] ?? $this->pdfToCairoBinary);
        $this->pdfToTextBinary = (string) ($state['pdftotext_binary'] ?? $this->pdfToTextBinary);
    }

    private function markActiveModel(string $modelName): void
    {
        $this->availableModels = collect($this->availableModels)
            ->map(static fn (array $model): array => [
                ...$model,
                'isConfigured' => $model['name'] === $modelName,
            ])
            ->all();
    }

    private function loadPipelineChecks(): void
    {
        $configuredModelInstalled = collect($this->availableModels)
            ->contains(static fn (array $model): bool => $model['isConfigured']);

        $ollamaReady = $this->connectionStatus === 'operational' && $configuredModelInstalled;
        $pdfSupportConfigured = filled($this->pdfInfoBinary)
            && filled($this->pdfToCairoBinary)
            && filled($this->pdfToTextBinary);

        $this->pipelineChecks = [
            [
                'label' => 'Vision receipt extraction',
                'status' => $ollamaReady ? 'ready' : 'attention',
                'detail' => $ollamaReady
                    ? 'Configured model is installed and ready for receipt images.'
                    : 'Configured model is unavailable or Ollama is not operational.',
            ],
            [
                'label' => 'Manual text label suggestions',
                'status' => $ollamaReady ? 'ready' : 'attention',
                'detail' => $ollamaReady
                    ? 'Structured JSON responses are available for manual WhatsApp expense labels.'
                    : 'Text-only label suggestions depend on the configured model being available.',
            ],
            [
                'label' => 'PDF receipt processing',
                'status' => $ollamaReady && ($pdfSupportConfigured || $this->popplerSkipped) ? ($pdfSupportConfigured ? 'ready' : 'attention') : 'attention',
                'detail' => $ollamaReady && $pdfSupportConfigured
                    ? 'Poppler binaries are configured for page inspection, text extraction, and rendering.'
                    : ($this->popplerSkipped
                        ? 'Poppler was skipped. Image receipts work; configure PDF binaries later for PDF receipts.'
                        : 'PDF parsing needs an operational model plus configured Poppler binaries.'),
            ],
            [
                'label' => 'Structured JSON cleanup',
                'status' => 'ready',
                'detail' => 'JSON mode is enforced and fenced markdown is stripped before decoding.',
            ],
        ];
    }

    private function loadActivityStats(): void
    {
        /** @var array<string, int|string> $statusCounts */
        $statusCounts = Expense::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $this->activityStats = [
            [
                'label' => 'Parsed',
                'value' => number_format((int) ($statusCounts['parsed'] ?? 0)),
                'description' => 'Receipts stored with extracted fields.',
            ],
            [
                'label' => 'Reviewed',
                'value' => number_format((int) ($statusCounts['reviewed'] ?? 0)),
                'description' => 'Receipts confirmed after review.',
            ],
            [
                'label' => 'Manual review',
                'value' => number_format((int) ($statusCounts['requires_manual_review'] ?? 0)),
                'description' => 'Receipts waiting for manual checks.',
            ],
            [
                'label' => 'PDF receipts',
                'value' => number_format(
                    Expense::query()
                        ->where('file_mime_type', 'application/pdf')
                        ->count(),
                ),
                'description' => 'Stored documents routed through PDF extraction.',
            ],
            [
                'label' => 'Image receipts',
                'value' => number_format(
                    Expense::query()
                        ->where('file_mime_type', 'like', 'image/%')
                        ->count(),
                ),
                'description' => 'Stored images routed through vision extraction.',
            ],
            [
                'label' => 'Text-only receipts',
                'value' => number_format(
                    Expense::query()
                        ->where(function ($query): void {
                            $query
                                ->whereNull('file_mime_type')
                                ->orWhere('file_mime_type', '');
                        })
                        ->count(),
                ),
                'description' => 'Receipts stored without an uploaded file.',
            ],
        ];

        $latestProcessedExpense = Expense::query()
            ->whereIn('status', ['parsed', 'reviewed', 'requires_manual_review'])
            ->latest('updated_at')
            ->first(['merchant_name', 'updated_at']);

        $this->latestReceiptActivity = $latestProcessedExpense === null
            ? 'No processed receipts yet.'
            : sprintf(
                'Latest processed receipt updated %s%s.',
                $latestProcessedExpense->updated_at?->diffForHumans() ?? 'recently',
                filled($latestProcessedExpense->merchant_name)
                    ? ' ('.$latestProcessedExpense->merchant_name.')'
                    : '',
            );
    }
}
