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

    public static function recommendedPullCommand(): string
    {
        return 'ollama pull '.self::RECOMMENDED_VISION_MODEL;
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
