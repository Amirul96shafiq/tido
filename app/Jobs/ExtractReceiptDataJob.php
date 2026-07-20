<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Prompts\ReceiptExtractionPrompt;
use App\Services\LabelMatcher;
use App\Services\OllamaService;
use App\Services\ReceiptParseNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExtractReceiptDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

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

        $imageContents = Storage::get($invoice->image_path);
        $base64Image = base64_encode($imageContents);

        $parsed = $ollama->parseReceipt($base64Image, ReceiptExtractionPrompt::build());

        if (! $parsed) {
            throw new \Exception('Ollama receipt extraction returned empty or invalid response.');
        }

        $normalized = $normalizer->normalize($parsed);

        $dateTime = $normalized['date_time'];
        $dateParsed = $dateTime !== null;
        $dateSane = $normalizer->isDateTimeSane($dateTime);

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
        $invoice->currency = $normalized['currency'];
        $invoice->payment_method = $this->resolvePaymentMethod($normalized['payment_method']);
        $invoice->raw_ai_response = $parsed;

        $needsManualReview = ! $dateParsed
            || ! $dateSane
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

    public function failed(\Throwable $exception): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if ($invoice) {
            $invoice->update(['status' => 'requires_manual_review']);
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

    protected function resolvePaymentMethod(mixed $value): ?PaymentMethod
    {
        return PaymentMethod::tryFromAi($value);
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

    protected function uniqueReceiptHash(Invoice $invoice): string
    {
        $dateTimeStr = $invoice->date_time
            ? $invoice->date_time->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');

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
