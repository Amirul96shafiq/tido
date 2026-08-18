<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasSectionNav;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
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

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

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

    /**
     * @var list<array{name: string, family: string, parameterSize: string, quantization: string, contextLength: int, sizeBytes: int, isConfigured: bool}>
     */
    public array $availableModels = [];

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
            ['label' => 'Models', 'id' => 'ollama-models'],
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
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $this->fetchStatus();

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
                $this->statusMessage = $count.' '.($count === 1 ? 'model' : 'models').' available.';
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
}
