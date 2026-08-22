<?php

declare(strict_types=1);

namespace App\Services\Ollama;

use App\Models\OllamaSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class OllamaSettings
{
    public const RECOMMENDED_VISION_MODEL = 'qwen2.5vl:7b';

    private ?OllamaSetting $cachedRecord = null;

    /**
     * @return list<array{
     *     name: string,
     *     tier: 'recommended'|'lighter'|'minimal',
     *     label: string,
     *     vramHint: string,
     *     sizeHint: string,
     * }>
     */
    public static function visionModelTiers(): array
    {
        return [
            [
                'name' => self::RECOMMENDED_VISION_MODEL,
                'tier' => 'recommended',
                'label' => 'Recommended',
                'vramHint' => '8 GB+ VRAM',
                'sizeHint' => '~6 GB download',
            ],
            [
                'name' => 'minicpm-v',
                'tier' => 'lighter',
                'label' => 'Lighter',
                'vramHint' => '~4 GB VRAM',
                'sizeHint' => '~2 GB download',
            ],
            [
                'name' => 'moondream',
                'tier' => 'minimal',
                'label' => 'Minimal',
                'vramHint' => '~2 GB VRAM or CPU',
                'sizeHint' => '~1.7 GB download',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function orderedTierModelNames(): array
    {
        return collect(self::visionModelTiers())
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     name: string,
     *     tier: 'recommended'|'lighter'|'minimal',
     *     label: string,
     *     vramHint: string,
     *     sizeHint: string,
     * }|null
     */
    public static function tierForModel(string $modelName): ?array
    {
        return collect(self::visionModelTiers())
            ->first(static fn (array $tier): bool => $tier['name'] === $modelName);
    }

    public static function pullCommandFor(string $modelName): string
    {
        return 'ollama pull '.$modelName;
    }

    public static function recommendedPullCommand(): string
    {
        return self::pullCommandFor(self::RECOMMENDED_VISION_MODEL);
    }

    public static function selectOptionLabel(string $modelName): string
    {
        $tier = self::tierForModel($modelName);

        if ($tier === null) {
            return $modelName;
        }

        return sprintf('%s (%s · %s)', $modelName, $tier['label'], $tier['vramHint']);
    }

    /**
     * @param  list<array{name: string}>  $models
     * @return array<string, string>
     */
    public static function selectOptionsForModels(array $models): array
    {
        $tierOrder = collect(self::orderedTierModelNames())->flip();

        $names = collect($models)
            ->pluck('name')
            ->filter(static fn (mixed $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values();

        $tiered = $names
            ->filter(static fn (string $name): bool => $tierOrder->has($name))
            ->sortBy(static fn (string $name): int => (int) $tierOrder->get($name))
            ->mapWithKeys(static fn (string $name): array => [$name => self::selectOptionLabel($name)]);

        $other = $names
            ->reject(static fn (string $name): bool => $tierOrder->has($name))
            ->sort()
            ->mapWithKeys(static fn (string $name): array => [$name => $name]);

        return $tiered->merge($other)->all();
    }

    /**
     * @return list<array{
     *     name: string,
     *     tier: 'recommended'|'lighter'|'minimal',
     *     label: string,
     *     vramHint: string,
     *     sizeHint: string,
     * }>
     */
    public static function lighterVisionModelTiers(): array
    {
        return collect(self::visionModelTiers())
            ->reject(static fn (array $tier): bool => $tier['tier'] === 'recommended')
            ->values()
            ->all();
    }

    public function record(): OllamaSetting
    {
        if ($this->cachedRecord instanceof OllamaSetting) {
            return $this->cachedRecord;
        }

        if (! Schema::hasTable('ollama_settings')) {
            return $this->cachedRecord = new OllamaSetting(['id' => OllamaSetting::SINGLETON_ID]);
        }

        return $this->cachedRecord = OllamaSetting::singleton();
    }

    public function host(): string
    {
        $host = $this->record()->host;

        return rtrim($host ?? (string) config('services.ollama.host'), '/');
    }

    public function model(): string
    {
        return (string) ($this->record()->model ?? config('services.ollama.model'));
    }

    public function timeout(): int
    {
        return (int) ($this->record()->timeout ?? config('services.ollama.timeout'));
    }

    public function numCtx(): int
    {
        return (int) ($this->record()->num_ctx ?? config('services.ollama.num_ctx'));
    }

    public function maxImageDimension(): int
    {
        return (int) ($this->record()->max_image_dimension ?? config('services.ollama.max_image_dimension'));
    }

    public function pdfinfoBinary(): string
    {
        return (string) ($this->record()->pdfinfo_binary ?? config('services.documents.pdfinfo_binary'));
    }

    public function pdftocairoBinary(): string
    {
        return (string) ($this->record()->pdftocairo_binary ?? config('services.documents.pdftocairo_binary'));
    }

    public function pdftotextBinary(): string
    {
        return (string) ($this->record()->pdftotext_binary ?? config('services.documents.pdftotext_binary'));
    }

    public function isSetupComplete(): bool
    {
        return $this->record()->setup_completed_at !== null;
    }

    public function usesSavedSettings(): bool
    {
        $record = $this->record();

        return filled($record->host)
            || filled($record->model)
            || $record->timeout !== null
            || $record->num_ctx !== null
            || $record->max_image_dimension !== null
            || filled($record->pdfinfo_binary)
            || filled($record->pdftocairo_binary)
            || filled($record->pdftotext_binary)
            || $record->setup_completed_at !== null;
    }

    /**
     * @param  array{
     *     host?: string|null,
     *     model?: string|null,
     *     timeout?: int|null,
     *     num_ctx?: int|null,
     *     max_image_dimension?: int|null,
     *     pdfinfo_binary?: string|null,
     *     pdftocairo_binary?: string|null,
     *     pdftotext_binary?: string|null,
     *     setup_completed_at?: Carbon|null,
     * }  $attributes
     */
    public function save(array $attributes): OllamaSetting
    {
        $record = OllamaSetting::singleton();
        $record->fill($attributes);
        $record->save();

        return $this->cachedRecord = $record->refresh();
    }
}
