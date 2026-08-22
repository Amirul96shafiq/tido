<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Expense;
use App\Models\ExpenseItem;
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
use App\Support\WhatsAppTypingCoordinator;
use App\Support\WhatsAppTypingSession;
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

    public function __construct(public int $expenseId)
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
        $expense = Expense::find($this->expenseId);

        if (! $expense || $expense->status !== 'pending') {
            return;
        }

        if (empty($expense->image_path)) {
            Log::info('ExtractReceiptDataJob skipped: expense has no image (manual text expense)', [
                'expense_id' => $this->expenseId,
            ]);

            return;
        }

        if (! Storage::exists($expense->image_path)) {
            Log::error('Expense image path does not exist', ['expense_id' => $this->expenseId]);
            $expense->update(['status' => 'failed']);

            return;
        }

        $this->startWhatsAppTypingIndicator($expense);

        $documentText = $documentPreparer->extractText($expense);
        $base64Pages = $documentPreparer->prepare($expense);
        $parsed = $expense->file_mime_type === 'application/pdf'
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
            'expense_id' => $expense->id,
            'currency' => $currencyDetection['currency'] ?? Expense::CURRENCY_UNKNOWN,
            'source' => $currencyDetection['source'],
            'rate_source' => $currencyDetection['rate_source'] ?? null,
        ]);

        $dateTime = $normalized['date_time'];
        $dateParsed = $dateTime !== null;
        $dateSane = $normalizer->isDateTimeSane($dateTime);
        $sourceAmountsReconcile = $normalizer->amountsReconcile($normalized);

        $expense->merchant_name = $normalized['merchant_name'];
        $expense->invoice_number = $normalized['invoice_number'];
        if ($dateParsed) {
            $expense->date_time = $dateTime;
        }
        $expense->subtotal = $normalized['subtotal'];
        $expense->total_tax = $normalized['total_tax'];
        $expense->discount_total = $normalized['discount_total'];
        $expense->rounding_amount = $normalized['rounding_amount'];
        $expense->total_amount = $normalized['total_amount'];
        $expense->currency = $normalized['currency'] ?? Expense::CURRENCY_UNKNOWN;
        $expense->original_currency = $normalized['currency'];
        $expense->original_total_amount = $normalized['total_amount'];
        $expense->currency_conversion_status = Expense::CONVERSION_PENDING;
        $expense->currency_conversion_rate = null;
        $expense->currency_conversion_date = null;
        $expense->currency_conversion_provider = null;
        $expense->currency_conversion_fetched_at = null;
        $expense->payment_method_id = $paymentMethodMatcher->matchId($normalized['payment_method']);
        $expense->raw_ai_response = array_merge($parsed, [
            'currency_detection' => $currencyDetection,
        ]);

        // Persist the source extraction before the outbound rate request so an unavailable
        // provider cannot make a foreign amount look like a MYR expense.
        $expense->save();

        try {
            $conversion = $currencyConversionService->convert(
                $normalized,
                $dateTime,
                $currencyDetection['rate'] ?? null,
            );
        } catch (CurrencyConversionException $exception) {
            $this->markCurrencyConversionFailure($expense, $exception);
            $this->notifyWhatsAppParsed($expense);

            return;
        }

        $normalized = $conversion['normalized'];
        $metadata = $conversion['metadata'];

        $expense->subtotal = $normalized['subtotal'];
        $expense->total_tax = $normalized['total_tax'];
        $expense->discount_total = $normalized['discount_total'];
        $expense->rounding_amount = $normalized['rounding_amount'];
        $expense->total_amount = $normalized['total_amount'];
        $expense->currency = Expense::CURRENCY_MYR;
        $expense->original_currency = $metadata['original_currency'];
        $expense->original_total_amount = $metadata['original_total_amount'];
        $expense->currency_conversion_status = $metadata['currency_conversion_status'];
        $expense->currency_conversion_rate = $metadata['currency_conversion_rate'];
        $expense->setAttribute('currency_conversion_date', $metadata['currency_conversion_date']);
        $expense->currency_conversion_provider = $metadata['currency_conversion_provider'];
        $expense->setAttribute('currency_conversion_fetched_at', $metadata['currency_conversion_fetched_at']);

        $needsManualReview = ! $dateParsed
            || ! $dateSane
            || ! $sourceAmountsReconcile
            || ! $normalizer->amountsReconcile($normalized);

        $expense->status = $needsManualReview ? 'requires_manual_review' : 'parsed';
        $expense->notes = $this->appendDateReviewNote($expense->notes, $dateParsed, $dateSane);
        $expense->receipt_hash = $this->uniqueReceiptHash($expense);
        $expense->save();

        $expense->expenseItems()->delete();

        foreach ($normalized['items'] as $item) {
            ExpenseItem::create([
                'expense_id' => $expense->id,
                'label_id' => $labelMatcher->matchId($item['label']),
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'serial_number' => $item['serial_number'],
            ]);
        }

        $this->notifyWhatsAppParsed($expense);

        Log::info('Expense parsed successfully via AI pipeline', [
            'expense_id' => $expense->id,
            'status' => $expense->status,
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
        $expense = Expense::find($this->expenseId);
        if ($expense) {
            $updates = ['status' => 'requires_manual_review'];

            if ($expense->currency_conversion_status === Expense::CONVERSION_PENDING) {
                $updates['currency_conversion_status'] = Expense::CONVERSION_FAILED;
            }

            $expense->update($updates);

            if ($expense->source === 'whatsapp') {
                WhatsAppTypingSession::deactivate($this->expenseId);
            }
        }

        Log::error('ExtractReceiptDataJob failed after maximum retries', [
            'expense_id' => $this->expenseId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function notifyWhatsAppParsed(Expense $expense): void
    {
        if ($expense->source !== 'whatsapp' || blank($expense->whatsapp_sender)) {
            return;
        }

        SendWhatsAppDocumentParsedJob::dispatch($expense->id);
    }

    protected function startWhatsAppTypingIndicator(Expense $expense): void
    {
        if ($expense->source !== 'whatsapp' || blank($expense->whatsapp_sender)) {
            return;
        }

        WhatsAppTypingCoordinator::startExpenseTyping($expense->id, (string) $expense->whatsapp_sender);
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
        Expense $expense,
        CurrencyConversionException $exception,
    ): void {
        $expense->currency_conversion_status = Expense::CONVERSION_FAILED;
        $expense->status = 'requires_manual_review';
        $expense->notes = $this->appendCurrencyReviewNote($expense->notes);
        $expense->receipt_hash = $this->uniqueReceiptHash($expense);
        $expense->save();

        Log::warning('Expense currency conversion requires manual review', [
            'expense_id' => $expense->id,
            'currency' => $expense->original_currency ?? Expense::CURRENCY_UNKNOWN,
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

    protected function uniqueReceiptHash(Expense $expense): string
    {
        $dateTimeStr = $expense->date_time->format('Y-m-d H:i:s');

        $base = hash(
            'sha256',
            ($expense->invoice_number ?? '').$dateTimeStr.$expense->total_amount
        );

        // Soft-deleted rows still occupy the unique index; include them in the collision check.
        $collision = Expense::withTrashed()
            ->where('receipt_hash', $base)
            ->where('id', '!=', $expense->id)
            ->exists();

        if (! $collision) {
            return $base;
        }

        return hash('sha256', $base.'|'.$expense->id);
    }
}
