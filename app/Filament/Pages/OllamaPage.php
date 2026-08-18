<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasSectionNav;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Models\Expense;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
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

    public string $connectionStatus = 'unknown';

    public string $statusMessage = '';

    public int $latencyMs = 0;

    public string $configuredModel = '';

    public string $host = '';

    public int $timeout = 120;

    public int $numCtx = 8192;

    public int $maxImageDimension = 1280;

    public string $pdfInfoBinary = '';

    public string $pdfToCairoBinary = '';

    public string $pdfToTextBinary = '';

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
            ['label' => 'Pipeline', 'id' => 'ollama-pipeline'],
            ['label' => 'Models', 'id' => 'ollama-models'],
            ['label' => 'Activity', 'id' => 'ollama-activity'],
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

    public function mount(): void
    {
        $this->loadConfig();
        $this->fetchStatus();
        $this->loadPipelineChecks();
        $this->loadActivityStats();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $this->fetchStatus();
                    $this->loadPipelineChecks();
                    $this->loadActivityStats();

                    Notification::make()
                        ->title('Status refreshed')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function loadConfig(): void
    {
        $this->host = rtrim((string) config('services.ollama.host'), '/');
        $this->configuredModel = (string) config('services.ollama.model');
        $this->timeout = (int) config('services.ollama.timeout');
        $this->numCtx = (int) config('services.ollama.num_ctx');
        $this->maxImageDimension = (int) config('services.ollama.max_image_dimension');
        $this->pdfInfoBinary = (string) config('services.documents.pdfinfo_binary');
        $this->pdfToCairoBinary = (string) config('services.documents.pdftocairo_binary');
        $this->pdfToTextBinary = (string) config('services.documents.pdftotext_binary');
    }

    private function fetchStatus(): void
    {
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(5)->get("{$this->host}/api/tags");

            $this->latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->failed()) {
                $this->connectionStatus = 'down';
                $this->statusMessage = 'Ollama responded with HTTP '.$response->status().'.';
                $this->availableModels = [];

                return;
            }

            $models = data_get($response->json(), 'models');

            if (! is_array($models)) {
                $this->connectionStatus = 'degraded';
                $this->statusMessage = 'Ollama responded but the model list was unexpected.';
                $this->availableModels = [];

                return;
            }

            $this->availableModels = collect($models)
                ->map(fn (mixed $model): array => [
                    'name' => (string) data_get($model, 'name', ''),
                    'family' => (string) data_get($model, 'details.family', '—'),
                    'parameterSize' => (string) data_get($model, 'details.parameter_size', '—'),
                    'quantization' => (string) data_get($model, 'details.quantization_level', '—'),
                    'contextLength' => (int) data_get($model, 'details.context_length', 0),
                    'sizeBytes' => (int) data_get($model, 'size', 0),
                    'isConfigured' => data_get($model, 'name') === $this->configuredModel,
                ])
                ->filter(fn (array $model): bool => $model['name'] !== '')
                ->values()
                ->all();

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
        } catch (Throwable $throwable) {
            $this->latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->connectionStatus = 'down';
            $this->statusMessage = 'Ollama is unreachable: '.$throwable->getMessage();
            $this->availableModels = [];
        }
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

    public function readyPipelineCheckCount(): int
    {
        return count(array_filter(
            $this->pipelineChecks,
            static fn (array $check): bool => $check['status'] === 'ready',
        ));
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
                'status' => $ollamaReady && $pdfSupportConfigured ? 'ready' : 'attention',
                'detail' => $ollamaReady && $pdfSupportConfigured
                    ? 'Poppler binaries are configured for page inspection, text extraction, and rendering.'
                    : 'PDF parsing needs an operational model plus configured Poppler binaries.',
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
