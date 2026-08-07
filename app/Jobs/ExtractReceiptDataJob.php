<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Prompts\PdfReceiptMergePrompt;
use App\Prompts\PdfReceiptPagePrompt;
use App\Prompts\ReceiptExtractionPrompt;
use App\Services\Currency\CurrencyConversionException;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\ReceiptCurrencyDetector;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\PaymentMethodMatcher;
use App\Services\ReceiptDocumentPreparer;
use App\Services\ReceiptParseNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExtractReceiptDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('receipts');
    }

    public function handle(
        OllamaService $ollama,
        ReceiptParseNormalizer $normalizer,
        LabelMatcher $labelMatcher,
        PaymentMethodMatcher $paymentMethodMatcher,
        ReceiptDocumentPreparer $documentPreparer,
        CurrencyConversionService $currencyConversionService,
        ReceiptCurrencyDetector $currencyDetector,
    ): void {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || $invoice->status !== 'pending') {
            return;
        }

        if (empty($invoice->image_path)) {
            Log::info('ExtractReceiptDataJob skipped: invoice has no image (manual text invoice)', [
                'invoice_id' => $this->invoiceId,
            ]);

            return;
        }

        if (! Storage::exists($invoice->image_path)) {
            Log::error('Invoice image path does not exist', ['invoice_id' => $this->invoiceId]);
            $invoice->update(['status' => 'failed']);

            return;
        }

        $documentText = $documentPreparer->extractText($invoice);
        $base64Pages = $documentPreparer->prepare($invoice);
        $parsed = $invoice->file_mime_type === 'application/pdf'
            ? $this->parsePdfDocument($ollama, $base64Pages)
            : $ollama->parseReceipt($base64Pages[0], ReceiptExtractionPrompt::build());

        if (! $parsed) {
            throw new RuntimeException('Ollama receipt extraction returned empty or invalid response.');
        }

        $normalized = $normalizer->normalize($parsed);

        $currencyDetection = $currencyDetector->detect(
            $documentText,
            $base64Pages,
            $normalized['currency'],
        );
        $normalized['currency'] = $currencyDetection['currency'];

        Log::info('Receipt currency detected from document content', [
            'invoice_id' => $invoice->id,
            'currency' => $currencyDetection['currency'] ?? Invoice::CURRENCY_UNKNOWN,
            'source' => $currencyDetection['source'],
            'rate_source' => $currencyDetection['rate_source'] ?? null,
        ]);

        $dateTime = $normalized['date_time'];
        $dateParsed = $dateTime !== null;
        $dateSane = $normalizer->isDateTimeSane($dateTime);
        $sourceAmountsReconcile = $normalizer->amountsReconcile($normalized);

        $invoice->merchant_name = $normalized['merchant_name'];
        $invoice->invoice_number = $normalized['invoice_number'];
        if ($dateParsed) {
            $invoice->date_time = $dateTime;
        }
        $invoice->subtotal = $normalized['subtotal'];
        $invoice->total_tax = $normalized['total_tax'];
        $invoice->discount_total = $normalized['discount_total'];
        $invoice->rounding_amount = $normalized['rounding_amount'];
        $invoice->total_amount = $normalized['total_amount'];
        $invoice->currency = $normalized['currency'] ?? Invoice::CURRENCY_UNKNOWN;
        $invoice->original_currency = $normalized['currency'];
        $invoice->original_total_amount = $normalized['total_amount'];
        $invoice->currency_conversion_status = Invoice::CONVERSION_PENDING;
        $invoice->currency_conversion_rate = null;
        $invoice->currency_conversion_date = null;
        $invoice->currency_conversion_provider = null;
        $invoice->currency_conversion_fetched_at = null;
        $invoice->payment_method_id = $paymentMethodMatcher->matchId($normalized['payment_method']);
        $invoice->raw_ai_response = array_merge($parsed, [
            'currency_detection' => $currencyDetection,
        ]);

        // Persist the source extraction before the outbound rate request so an unavailable
        // provider cannot make a foreign amount look like a MYR expense.
        $invoice->save();

        try {
            $conversion = $currencyConversionService->convert(
                $normalized,
                $dateTime,
                $currencyDetection['rate'] ?? null,
            );
        } catch (CurrencyConversionException $exception) {
            $this->markCurrencyConversionFailure($invoice, $exception);
            $this->notifyWhatsAppParsed($invoice);

            return;
        }

        $normalized = $conversion['normalized'];
        $metadata = $conversion['metadata'];

        $invoice->subtotal = $normalized['subtotal'];
        $invoice->total_tax = $normalized['total_tax'];
        $invoice->discount_total = $normalized['discount_total'];
        $invoice->rounding_amount = $normalized['rounding_amount'];
        $invoice->total_amount = $normalized['total_amount'];
        $invoice->currency = Invoice::CURRENCY_MYR;
        $invoice->original_currency = $metadata['original_currency'];
        $invoice->original_total_amount = $metadata['original_total_amount'];
        $invoice->currency_conversion_status = $metadata['currency_conversion_status'];
        $invoice->currency_conversion_rate = $metadata['currency_conversion_rate'];
        $invoice->setAttribute('currency_conversion_date', $metadata['currency_conversion_date']);
        $invoice->currency_conversion_provider = $metadata['currency_conversion_provider'];
        $invoice->setAttribute('currency_conversion_fetched_at', $metadata['currency_conversion_fetched_at']);

        $needsManualReview = ! $dateParsed
            || ! $dateSane
            || ! $sourceAmountsReconcile
            || ! $normalizer->amountsReconcile($normalized);

        $invoice->status = $needsManualReview ? 'requires_manual_review' : 'parsed';
        $invoice->notes = $this->appendDateReviewNote($invoice->notes, $dateParsed, $dateSane);
        $invoice->receipt_hash = $this->uniqueReceiptHash($invoice);
        $invoice->save();

        $invoice->invoiceItems()->delete();

        foreach ($normalized['items'] as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'label_id' => $labelMatcher->matchId($item['label']),
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'serial_number' => $item['serial_number'],
            ]);
        }

        $this->notifyWhatsAppParsed($invoice);

        Log::info('Invoice parsed successfully via AI pipeline', [
            'invoice_id' => $invoice->id,
            'status' => $invoice->status,
        ]);
    }

    /**
     * @param  list<string>  $base64Pages
     * @return array<string, mixed>|null
     */
    protected function parsePdfDocument(OllamaService $ollama, array $base64Pages): ?array
    {
        $pageCount = count($base64Pages);

        if ($pageCount < 1) {
            return null;
        }

        $pageResults = [];

        foreach ($base64Pages as $index => $base64Page) {
            $pageResult = $ollama->generateJson(
                PdfReceiptPagePrompt::build($index + 1, $pageCount),
                [$base64Page],
            );

            if ($pageResult === null) {
                return null;
            }

            $pageResults[] = $pageResult;
        }

        if ($pageCount === 1) {
            return $pageResults[0];
        }

        return $ollama->generateJson(PdfReceiptMergePrompt::build($pageResults));
    }

    public function failed(\Throwable $exception): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if ($invoice) {
            $updates = ['status' => 'requires_manual_review'];

            if ($invoice->currency_conversion_status === Invoice::CONVERSION_PENDING) {
                $updates['currency_conversion_status'] = Invoice::CONVERSION_FAILED;
            }

            $invoice->update($updates);
        }

        Log::error('ExtractReceiptDataJob failed after maximum retries', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function notifyWhatsAppParsed(Invoice $invoice): void
    {
        if ($invoice->source !== 'whatsapp' || blank($invoice->whatsapp_sender)) {
            return;
        }

        SendWhatsAppDocumentParsedJob::dispatch($invoice->id);
    }

    protected function appendDateReviewNote(?string $existingNotes, bool $dateParsed, bool $dateSane): ?string
    {
        $marker = null;
        if (! $dateParsed) {
            $marker = '[AI] Receipt date/time could not be parsed.';
        } elseif (! $dateSane) {
            $marker = '[AI] Receipt date/time looks implausible and needs review.';
        }

        if ($marker === null) {
            return $existingNotes;
        }

        $notes = trim((string) $existingNotes);
        if ($notes !== '' && str_contains($notes, $marker)) {
            return $notes;
        }

        $markerHtml = '<p>'.$marker.'</p>';

        return $notes === '' ? $markerHtml : $notes.$markerHtml;
    }

    protected function markCurrencyConversionFailure(
        Invoice $invoice,
        CurrencyConversionException $exception,
    ): void {
        $invoice->currency_conversion_status = Invoice::CONVERSION_FAILED;
        $invoice->status = 'requires_manual_review';
        $invoice->notes = $this->appendCurrencyReviewNote($invoice->notes);
        $invoice->receipt_hash = $this->uniqueReceiptHash($invoice);
        $invoice->save();

        Log::warning('Invoice currency conversion requires manual review', [
            'invoice_id' => $invoice->id,
            'currency' => $invoice->original_currency ?? Invoice::CURRENCY_UNKNOWN,
            'reason' => $exception->getMessage(),
        ]);
    }

    protected function appendCurrencyReviewNote(?string $existingNotes): string
    {
        $marker = '[AI] Currency conversion could not be completed; verify the source amount and rate.';
        $notes = trim((string) $existingNotes);

        if ($notes !== '' && str_contains($notes, $marker)) {
            return $notes;
        }

        $markerHtml = '<p>'.$marker.'</p>';

        return $notes === '' ? $markerHtml : $notes.$markerHtml;
    }

    protected function uniqueReceiptHash(Invoice $invoice): string
    {
        $dateTimeStr = $invoice->date_time->format('Y-m-d H:i:s');

        $base = hash(
            'sha256',
            ($invoice->invoice_number ?? '').$dateTimeStr.$invoice->total_amount
        );

        // Soft-deleted rows still occupy the unique index; include them in the collision check.
        $collision = Invoice::withTrashed()
            ->where('receipt_hash', $base)
            ->where('id', '!=', $invoice->id)
            ->exists();

        if (! $collision) {
            return $base;
        }

        return hash('sha256', $base.'|'.$invoice->id);
    }
}
