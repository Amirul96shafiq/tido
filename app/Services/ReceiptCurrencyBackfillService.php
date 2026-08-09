<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseItem;
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
        Expense $expense,
        ?string $sourceCurrency = null,
        ?float $rateOverride = null,
    ): bool {
        if ($expense->currency_conversion_status === Expense::CONVERSION_CONVERTED
            && $expense->currency === Expense::CURRENCY_MYR) {
            $notes = $this->removeCurrencyReviewNote($expense->notes);

            if ($notes !== $expense->notes) {
                $expense->notes = $notes;
                $expense->save();
            }

            return true;
        }

        $parsed = $expense->raw_ai_response;
        if (! is_array($parsed)) {
            $this->markFailure($expense);

            return false;
        }

        $normalized = $this->normalizer->normalize($parsed);
        if ($sourceCurrency !== null) {
            $sourceCurrency = $this->normalizer->normalizeCurrency($sourceCurrency);

            if ($sourceCurrency === null) {
                $this->markFailure($expense);

                return false;
            }
        }

        if ($sourceCurrency === null && $this->isCurrencyCode((string) $expense->currency)
            && $expense->currency !== Expense::CURRENCY_MYR) {
            // Existing foreign rows may have persisted the detected code before conversion
            // was introduced; that stored value is the source evidence for this backfill.
            $normalized['currency'] = strtoupper((string) $expense->currency);
        }

        if ($sourceCurrency !== null) {
            $normalized['currency'] = $sourceCurrency;
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
        $expense->setAttribute('currency_conversion_date', null);
        $expense->currency_conversion_provider = null;
        $expense->setAttribute('currency_conversion_fetched_at', null);
        $expense->save();

        try {
            $conversion = $this->currencyConversion->convert(
                $normalized,
                $normalized['date_time'],
                $rateOverride,
            );
        } catch (CurrencyConversionException $exception) {
            $this->markFailure($expense);

            return false;
        }

        $converted = $conversion['normalized'];
        $metadata = $conversion['metadata'];
        $needsManualReview = ! $this->normalizer->isDateTimeSane($normalized['date_time'])
            || ! $this->normalizer->amountsReconcile($normalized)
            || ! $this->normalizer->amountsReconcile($converted);

        DB::transaction(function () use ($expense, $converted, $metadata, $needsManualReview): void {
            if ($converted['date_time'] !== null) {
                $expense->date_time = $converted['date_time'];
            }

            $expense->subtotal = $converted['subtotal'];
            $expense->total_tax = $converted['total_tax'];
            $expense->discount_total = $converted['discount_total'];
            $expense->rounding_amount = $converted['rounding_amount'];
            $expense->total_amount = $converted['total_amount'];
            $expense->currency = Expense::CURRENCY_MYR;
            $expense->original_currency = $metadata['original_currency'];
            $expense->original_total_amount = $metadata['original_total_amount'];
            $expense->currency_conversion_status = $metadata['currency_conversion_status'];
            $expense->currency_conversion_rate = $metadata['currency_conversion_rate'];
            $expense->setAttribute('currency_conversion_date', $metadata['currency_conversion_date']);
            $expense->currency_conversion_provider = $metadata['currency_conversion_provider'];
            $expense->setAttribute('currency_conversion_fetched_at', $metadata['currency_conversion_fetched_at']);
            if ($expense->status !== 'reviewed') {
                $expense->status = $needsManualReview ? 'requires_manual_review' : 'parsed';
            }
            $expense->notes = $this->removeCurrencyReviewNote($expense->notes);
            $expense->receipt_hash = $this->uniqueReceiptHash($expense);
            $expense->save();

            $expense->expenseItems()->delete();

            foreach ($converted['items'] as $item) {
                ExpenseItem::create([
                    'expense_id' => $expense->id,
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

    private function markFailure(Expense $expense): void
    {
        $expense->currency_conversion_status = Expense::CONVERSION_FAILED;
        $expense->status = 'requires_manual_review';
        $expense->notes = $this->appendReviewNote($expense->notes);
        $expense->save();
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

    private function uniqueReceiptHash(Expense $expense): string
    {
        $dateTime = $expense->date_time->format('Y-m-d H:i:s');
        $base = hash('sha256', ($expense->invoice_number ?? '').$dateTime.$expense->total_amount);

        $collision = Expense::withTrashed()
            ->where('receipt_hash', $base)
            ->where('id', '!=', $expense->id)
            ->exists();

        return $collision ? hash('sha256', $base.'|'.$expense->id) : $base;
    }

    private function isCurrencyCode(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', strtoupper(trim($currency))) === 1;
    }
}
