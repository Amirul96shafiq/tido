<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use App\Prompts\ManualInvoiceLabelPrompt;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseManualWhatsAppInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $invoiceId)
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
        $invoice = Invoice::with('invoiceItems')->find($this->invoiceId);

        if (! $invoice || $invoice->status !== 'pending') {
            return;
        }

        if ($invoice->source !== 'whatsapp' || filled($invoice->image_path)) {
            return;
        }

        $items = $invoice->invoiceItems;

        if ($items->isEmpty()) {
            $invoice->update(['status' => 'requires_manual_review']);
            $this->notifyParsed($invoice);

            return;
        }

        $descriptions = $items
            ->map(static fn ($item): string => (string) $item->description)
            ->values()
            ->all();

        $parsed = $ollama->generateJson(ManualInvoiceLabelPrompt::build($descriptions));

        if (! $parsed || ! isset($parsed['items']) || ! is_array($parsed['items'])) {
            throw new \Exception('Ollama manual invoice label classification returned empty or invalid response.');
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

        $raw = is_array($invoice->raw_ai_response) ? $invoice->raw_ai_response : [];
        $raw['label_classification'] = $parsed;
        $invoice->raw_ai_response = $raw;
        $invoice->status = 'requires_manual_review';
        $invoice->save();

        $this->notifyParsed($invoice);

        Log::info('Manual WhatsApp invoice labels applied', [
            'invoice_id' => $invoice->id,
            'status' => $invoice->status,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if ($invoice && $invoice->status === 'pending') {
            $invoice->update(['status' => 'requires_manual_review']);
            $this->notifyParsed($invoice);
        }

        Log::error('ParseManualWhatsAppInvoiceJob failed after maximum retries', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function notifyParsed(Invoice $invoice): void
    {
        if ($invoice->source !== 'whatsapp' || blank($invoice->whatsapp_sender)) {
            return;
        }

        SendWhatsAppManualInvoiceParsedJob::dispatch($invoice->id);
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
