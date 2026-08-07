<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Currency\CurrencyConversionException;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Support\Facades\DB;

final class ReceiptCurrencyBackfillService
{
    private const CURRENCY_REVIEW_MARKER = '[AI] Currency conversion could not be completed; verify the source amount and rate.';

    public function __construct(
        private readonly ReceiptParseNormalizer $normalizer,
        private readonly CurrencyConversionService $currencyConversion,
        private readonly LabelMatcher $labelMatcher,
    ) {}

    public function convert(
        Invoice $invoice,
        ?string $sourceCurrency = null,
        ?float $rateOverride = null,
    ): bool {
        if ($invoice->currency_conversion_status === Invoice::CONVERSION_CONVERTED
            && $invoice->currency === Invoice::CURRENCY_MYR) {
            $notes = $this->removeCurrencyReviewNote($invoice->notes);

            if ($notes !== $invoice->notes) {
                $invoice->notes = $notes;
                $invoice->save();
            }

            return true;
        }

        $parsed = $invoice->raw_ai_response;
        if (! is_array($parsed)) {
            $this->markFailure($invoice);

            return false;
        }

        $normalized = $this->normalizer->normalize($parsed);
        if ($sourceCurrency !== null) {
            $sourceCurrency = $this->normalizer->normalizeCurrency($sourceCurrency);

            if ($sourceCurrency === null) {
                $this->markFailure($invoice);

                return false;
            }
        }

        if ($sourceCurrency === null && $this->isCurrencyCode((string) $invoice->currency)
            && $invoice->currency !== Invoice::CURRENCY_MYR) {
            // Existing foreign rows may have persisted the detected code before conversion
            // was introduced; that stored value is the source evidence for this backfill.
            $normalized['currency'] = strtoupper((string) $invoice->currency);
        }

        if ($sourceCurrency !== null) {
            $normalized['currency'] = $sourceCurrency;
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
        $invoice->setAttribute('currency_conversion_date', null);
        $invoice->currency_conversion_provider = null;
        $invoice->setAttribute('currency_conversion_fetched_at', null);
        $invoice->save();

        try {
            $conversion = $this->currencyConversion->convert(
                $normalized,
                $normalized['date_time'],
                $rateOverride,
            );
        } catch (CurrencyConversionException $exception) {
            $this->markFailure($invoice);

            return false;
        }

        $converted = $conversion['normalized'];
        $metadata = $conversion['metadata'];

        DB::transaction(function () use ($invoice, $converted, $metadata): void {
            if ($converted['date_time'] !== null) {
                $invoice->date_time = $converted['date_time'];
            }

            $invoice->subtotal = $converted['subtotal'];
            $invoice->total_tax = $converted['total_tax'];
            $invoice->discount_total = $converted['discount_total'];
            $invoice->rounding_amount = $converted['rounding_amount'];
            $invoice->total_amount = $converted['total_amount'];
            $invoice->currency = Invoice::CURRENCY_MYR;
            $invoice->original_currency = $metadata['original_currency'];
            $invoice->original_total_amount = $metadata['original_total_amount'];
            $invoice->currency_conversion_status = $metadata['currency_conversion_status'];
            $invoice->currency_conversion_rate = $metadata['currency_conversion_rate'];
            $invoice->setAttribute('currency_conversion_date', $metadata['currency_conversion_date']);
            $invoice->currency_conversion_provider = $metadata['currency_conversion_provider'];
            $invoice->setAttribute('currency_conversion_fetched_at', $metadata['currency_conversion_fetched_at']);
            $invoice->notes = $this->removeCurrencyReviewNote($invoice->notes);
            $invoice->receipt_hash = $this->uniqueReceiptHash($invoice);
            $invoice->save();

            $invoice->invoiceItems()->delete();

            foreach ($converted['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'label_id' => $this->labelMatcher->matchId($item['label']),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'serial_number' => $item['serial_number'],
                ]);
            }
        });

        return true;
    }

    private function markFailure(Invoice $invoice): void
    {
        $invoice->currency_conversion_status = Invoice::CONVERSION_FAILED;
        $invoice->status = 'requires_manual_review';
        $invoice->notes = $this->appendReviewNote($invoice->notes);
        $invoice->save();
    }

    private function appendReviewNote(?string $existingNotes): string
    {
        $marker = self::CURRENCY_REVIEW_MARKER;
        $notes = trim((string) $existingNotes);

        if ($notes !== '' && str_contains($notes, $marker)) {
            return $notes;
        }

        $markerHtml = '<p>'.$marker.'</p>';

        return $notes === '' ? $markerHtml : $notes.$markerHtml;
    }

    private function removeCurrencyReviewNote(?string $existingNotes): ?string
    {
        $notes = trim(str_replace(
            '<p>'.self::CURRENCY_REVIEW_MARKER.'</p>',
            '',
            (string) $existingNotes,
        ));

        return $notes === '' ? null : $notes;
    }

    private function uniqueReceiptHash(Invoice $invoice): string
    {
        $dateTime = $invoice->date_time->format('Y-m-d H:i:s');
        $base = hash('sha256', ($invoice->invoice_number ?? '').$dateTime.$invoice->total_amount);

        $collision = Invoice::withTrashed()
            ->where('receipt_hash', $base)
            ->where('id', '!=', $invoice->id)
            ->exists();

        return $collision ? hash('sha256', $base.'|'.$invoice->id) : $base;
    }

    private function isCurrencyCode(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', strtoupper(trim($currency))) === 1;
    }
}
