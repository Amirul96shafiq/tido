<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Expense;
use App\Prompts\ManualExpenseLabelPrompt;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseManualWhatsAppExpenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $expenseId)
    {
        $this->onQueue('receipts');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(OllamaService $ollama, LabelMatcher $labelMatcher): void
    {
        $expense = Expense::with('expenseItems')->find($this->expenseId);

        if (! $expense || $expense->status !== 'pending') {
            return;
        }

        if ($expense->source !== 'whatsapp' || filled($expense->image_path)) {
            return;
        }

        $items = $expense->expenseItems;

        if ($items->isEmpty()) {
            $expense->update(['status' => 'requires_manual_review']);
            $this->notifyParsed($expense);

            return;
        }

        $descriptions = $items
            ->map(static fn ($item): string => (string) $item->description)
            ->values()
            ->all();

        $parsed = $ollama->generateJson(ManualExpenseLabelPrompt::build($descriptions));

        if (! $parsed || ! isset($parsed['items']) || ! is_array($parsed['items'])) {
            throw new \Exception('Ollama manual expense label classification returned empty or invalid response.');
        }

        $labelsByDescription = $this->indexLabelsByDescription($parsed['items']);

        foreach ($items as $item) {
            $description = (string) $item->description;
            $labelName = $labelsByDescription[$this->normalizeKey($description)]
                ?? $labelsByDescription[$this->normalizeKey(mb_strtolower($description))]
                ?? null;

            $item->label_id = $labelMatcher->matchId($labelName);
            $item->save();
        }

        $raw = is_array($expense->raw_ai_response) ? $expense->raw_ai_response : [];
        $raw['label_classification'] = $parsed;
        $expense->raw_ai_response = $raw;
        $expense->status = 'requires_manual_review';
        $expense->save();

        $this->notifyParsed($expense);

        Log::info('Manual WhatsApp expense labels applied', [
            'expense_id' => $expense->id,
            'status' => $expense->status,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $expense = Expense::find($this->expenseId);
        if ($expense && $expense->status === 'pending') {
            $expense->update(['status' => 'requires_manual_review']);
            $this->notifyParsed($expense);
        }

        Log::error('ParseManualWhatsAppExpenseJob failed after maximum retries', [
            'expense_id' => $this->expenseId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function notifyParsed(Expense $expense): void
    {
        if ($expense->source !== 'whatsapp' || blank($expense->whatsapp_sender)) {
            return;
        }

        SendWhatsAppManualExpenseParsedJob::dispatch($expense->id);
    }

    /**
     * @param  list<mixed>  $items
     * @return array<string, string>
     */
    protected function indexLabelsByDescription(array $items): array
    {
        $indexed = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));

            if ($description === '' || $label === '') {
                continue;
            }

            $indexed[$this->normalizeKey($description)] = $label;
            $indexed[$this->normalizeKey(mb_strtolower($description))] = $label;
        }

        return $indexed;
    }

    protected function normalizeKey(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
